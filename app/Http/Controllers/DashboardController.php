<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\Balance;
use App\Models\TradingAccount;
use App\Services\AccountMetricsService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $user->ensureDefaultTradingAccount();

        $account = $user->activeTradingAccount();
        $accountId = $account?->id;

        // summarize() already loads all positions + fills once; reuse its result
        $summary = $account ? app(AccountMetricsService::class)->summarize($account) : [
            'balance'             => 0,
            'equity'              => 0,
            'floating_pnl'        => 0,
            'realized_pnl'        => 0,
            'net_profit'          => 0,
            'net_loss'            => 0,
            'total_trades'        => 0,
            'win_rate'            => 0,
            'total_brokerage'     => 0,
            'open_positions_count'=> 0,
            'has_initial_balance' => false,
        ];

        // Pull scalar stats straight from the summary — no extra queries needed
        $totalTrades     = $summary['total_trades'];
        $winRate         = $summary['win_rate'];
        $netProfit       = $summary['net_profit'];
        $netLoss         = $summary['net_loss'];
        $totalCommissions= $summary['total_brokerage'];
        $accountBalance  = $summary['balance'];
        $floatingPnL     = $summary['floating_pnl'];
        $equity          = $summary['equity'];
        $profitLoss      = $summary['realized_pnl'];

        // Derived counts reused from summary
        $winningTrades   = $totalTrades > 0 ? (int) round($winRate / 100 * $totalTrades) : 0;
        $losingTrades    = $totalTrades - $winningTrades;

        // ── Daily P&L for calendar widget (SQL aggregate, single query) ───────
        $dailyPnL = $this->buildPositionQuery($accountId)
            ->whereNotNull('close_datetime')
            ->whereNotNull('realized_pnl')
            ->selectRaw('DATE(close_datetime) as date, SUM(realized_pnl) as total_pnl')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // ── Monthly bar chart (SQL aggregate, single query) ───────────────────
        $daysInMonth    = now()->daysInMonth;
        $monthlyDailyPnL = $this->buildPositionQuery($accountId)
            ->whereNotNull('close_datetime')
            ->whereNotNull('realized_pnl')
            ->whereYear('close_datetime', now()->year)
            ->whereMonth('close_datetime', now()->month)
            ->selectRaw("CAST(strftime('%d', close_datetime) AS INTEGER) as day, SUM(realized_pnl) as total_pnl")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $chartLabels = [];
        $chartData   = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $chartLabels[] = $day;
            $chartData[]   = isset($monthlyDailyPnL[$day]) ? round($monthlyDailyPnL[$day]->total_pnl, 2) : 0;
        }

        // ── 5 most recent closed trades ───────────────────────────────────────
        $recentTrades = $this->buildPositionQuery($accountId)
            ->with('instrument')
            ->whereNotNull('close_datetime')
            ->whereNotNull('realized_pnl')
            ->orderBy('close_datetime', 'desc')
            ->limit(5)
            ->get();

        // ── Balance & P&L history charts ──────────────────────────────────────
        $balanceHistory = $this->calculateBalanceHistory($accountId);
        $pnlHistory     = $this->calculatePnLHistory($accountId);

        return view('dashboard', compact(
            'totalTrades', 'winningTrades', 'losingTrades', 'winRate',
            'netProfit', 'netLoss', 'totalCommissions',
            'accountBalance', 'floatingPnL', 'equity', 'profitLoss',
            'dailyPnL', 'recentTrades',
            'chartLabels', 'chartData',
            'balanceHistory', 'pnlHistory'
        ));
    }

    // ─── Shared query builder ──────────────────────────────────────────────────

    private function buildPositionQuery(?int $accountId)
    {
        return Position::whereHas('instrument', function ($q) use ($accountId) {
            $q->where('user_id', auth()->id());
            if ($accountId) {
                $q->where('trading_account_id', $accountId);
            }
        });
    }

    // ─── Balance history ───────────────────────────────────────────────────────

    /**
     * Build the daily running-balance series.
     * Replaces the old approach that loaded every position row into PHP just
     * to call ->format('Y-m-d') in a loop.  We now use a single SQL GROUP BY
     * for trade P&L and keep only the tiny Balance rows in PHP.
     */
    private function calculateBalanceHistory(?int $accountId = null): array
    {
        $initialBalances = Balance::where('user_id', auth()->id())
            ->where('type', 'initial')
            ->when($accountId, fn ($q) => $q->where('trading_account_id', $accountId))
            ->get();

        $balanceTransactions = Balance::where('user_id', auth()->id())
            ->whereIn('type', ['deposit', 'withdrawal'])
            ->when($accountId, fn ($q) => $q->where('trading_account_id', $accountId))
            ->orderBy('date')
            ->get();

        // Daily trade P&L aggregated in SQL — no full position load
        $tradePnlByDay = $this->buildPositionQuery($accountId)
            ->whereNotNull('close_datetime')
            ->whereNotNull('realized_pnl')
            ->selectRaw('DATE(close_datetime) as date, SUM(realized_pnl) as total_pnl')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total_pnl', 'date');

        // Build a unified timeline keyed by date
        $transactionsByDate = [];

        foreach ($initialBalances as $ib) {
            $transactionsByDate[$ib->date->format('Y-m-d')][] = (float) $ib->amount;
        }
        foreach ($balanceTransactions as $tx) {
            $amount = $tx->type === 'withdrawal' ? -(float) $tx->amount : (float) $tx->amount;
            $transactionsByDate[$tx->date->format('Y-m-d')][] = $amount;
        }
        foreach ($tradePnlByDay as $date => $pnl) {
            $transactionsByDate[$date][] = (float) $pnl;
        }

        if (empty($transactionsByDate)) {
            return ['labels' => [], 'data' => [], 'startingBalance' => 0];
        }

        ksort($transactionsByDate);
        $startDate = array_key_first($transactionsByDate);

        $balanceByDay  = [];
        $runningBalance = 0.0;
        $currentDate   = \Carbon\Carbon::parse($startDate);
        $today         = now();

        while ($currentDate->lte($today)) {
            $dateStr = $currentDate->format('Y-m-d');
            if (isset($transactionsByDate[$dateStr])) {
                foreach ($transactionsByDate[$dateStr] as $amount) {
                    $runningBalance += $amount;
                }
            }
            $balanceByDay[] = ['date' => $dateStr, 'balance' => round($runningBalance, 2)];
            $currentDate->addDay();
        }

        return [
            'labels'         => array_column($balanceByDay, 'date'),
            'data'           => array_column($balanceByDay, 'balance'),
            'startingBalance'=> (float) $initialBalances->sum('amount'),
        ];
    }

    // ─── Cumulative P&L history ────────────────────────────────────────────────

    /**
     * Cumulative profit / loss lines for the chart.
     * Uses a SQL GROUP BY per day instead of loading every position row.
     */
    private function calculatePnLHistory(?int $accountId = null): array
    {
        // Aggregate per-day profit and loss sums in SQL
        $dailyRows = $this->buildPositionQuery($accountId)
            ->whereNotNull('close_datetime')
            ->whereNotNull('realized_pnl')
            ->selectRaw('DATE(close_datetime) as date, SUM(CASE WHEN realized_pnl > 0 THEN realized_pnl ELSE 0 END) as day_profit, SUM(CASE WHEN realized_pnl < 0 THEN ABS(realized_pnl) ELSE 0 END) as day_loss')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        if ($dailyRows->isEmpty()) {
            return ['labels' => [], 'profitData' => [], 'lossData' => []];
        }

        $startDate      = $dailyRows->keys()->first();
        $pnlByDate      = [];
        $cumProfit      = 0.0;
        $cumLoss        = 0.0;
        $currentDate    = \Carbon\Carbon::parse($startDate);
        $today          = now();

        while ($currentDate->lte($today)) {
            $dateStr = $currentDate->format('Y-m-d');
            if (isset($dailyRows[$dateStr])) {
                $cumProfit += (float) $dailyRows[$dateStr]->day_profit;
                $cumLoss   += (float) $dailyRows[$dateStr]->day_loss;
            }
            $pnlByDate[] = [
                'date'   => $dateStr,
                'profit' => round($cumProfit, 2),
                'loss'   => round($cumLoss, 2),
            ];
            $currentDate->addDay();
        }

        return [
            'labels'     => array_column($pnlByDate, 'date'),
            'profitData' => array_column($pnlByDate, 'profit'),
            'lossData'   => array_column($pnlByDate, 'loss'),
        ];
    }
}

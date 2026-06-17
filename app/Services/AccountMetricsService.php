<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Position;
use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;

class AccountMetricsService
{
    /**
     * Summarize an account's metrics.
     *
     * Scalar stats (counts, sums) are resolved via SQL aggregates so we only
     * load open positions into PHP — those are needed to call floatingPnL()
     * which depends on the instrument's current_price and fills.
     */
    public function summarize(TradingAccount $account): array
    {
        // ── SQL aggregate for closed positions ────────────────────────────────
        $closed = DB::table('positions')
            ->join('instruments', 'positions.instrument_id', '=', 'instruments.id')
            ->where('instruments.trading_account_id', $account->id)
            ->whereNotNull('positions.close_datetime')
            ->whereNotNull('positions.realized_pnl')
            ->selectRaw('
                COUNT(*)                                                  AS total_trades,
                SUM(CASE WHEN realized_pnl > 0 THEN 1 ELSE 0 END)       AS winning_trades,
                COALESCE(SUM(realized_pnl), 0)                           AS realized_pnl,
                COALESCE(SUM(CASE WHEN realized_pnl > 0 THEN realized_pnl ELSE 0 END), 0) AS net_profit,
                COALESCE(ABS(SUM(CASE WHEN realized_pnl < 0 THEN realized_pnl ELSE 0 END)), 0) AS net_loss
            ')
            ->first();

        $totalTrades   = (int)   ($closed->total_trades   ?? 0);
        $winningTrades = (int)   ($closed->winning_trades ?? 0);
        $realizedPnL   = round((float) ($closed->realized_pnl ?? 0), 2);
        $netProfit     = round((float) ($closed->net_profit   ?? 0), 2);
        $netLoss       = round((float) ($closed->net_loss     ?? 0), 2);

        // ── Balance math ─────────────────────────────────────────────────────
        $initialBalance = (float) ($account->starting_balance ?? 0);

        $deposits = Balance::where('trading_account_id', $account->id)
            ->where('type', 'deposit')
            ->sum('amount');

        $withdrawals = Balance::where('trading_account_id', $account->id)
            ->where('type', 'withdrawal')
            ->sum('amount');

        $balance = round($initialBalance + $deposits - $withdrawals, 2);

        // ── Open positions — load only what floatingPnL() needs ──────────────
        $openPositions = Position::with(['instrument.tradingAccount', 'fills'])
            ->join('instruments', 'positions.instrument_id', '=', 'instruments.id')
            ->where('instruments.trading_account_id', $account->id)
            ->whereNull('positions.close_datetime')
            ->select('positions.*')
            ->get();

        $floatingPnL   = round($this->calculateFloatingPnL($openPositions), 2);

        // ── Equity: use override if set, otherwise compute from balance + P&L ─
        $equity = $account->equity_override !== null
            ? round((float) $account->equity_override, 2)
            : round($balance + $realizedPnL + $floatingPnL, 2);

        // ── Brokerage: aggregated in SQL, open-position brokerage in PHP ─────
        $closedBrokerage = $this->closedBrokerageForAccount($account);
        $openBrokerage   = round(
            (float) $openPositions->sum(fn (Position $p) => $p->entryBrokerageAmount()),
            2
        );
        $totalBrokerage = round($closedBrokerage + $openBrokerage, 2);

        return [
            'total_trades'        => $totalTrades,
            'win_rate'            => $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0,
            'net_profit'          => $netProfit,
            'net_loss'            => $netLoss,
            'realized_pnl'        => $realizedPnL,
            'balance'             => $balance,
            'floating_pnl'        => $floatingPnL,
            'equity'              => $equity,
            'total_brokerage'     => $totalBrokerage,
            'open_positions_count'=> $openPositions->count(),
            'has_initial_balance' => $account->starting_balance !== null,
        ];
    }

    private function calculateFloatingPnL($openPositions): float
    {
        return round(
            (float) $openPositions->sum(function (Position $p) {
                // Marked positions: use mark-price realized PnL (entry + exit brokerage)
                if ($p->isMarked()) {
                    return $p->markRealizedPnL() ?? 0;
                }
                return $p->floatingPnL() ?? 0;
            }),
            2
        );
    }

    /**
     * Approximate closed-position brokerage using SQL:
     * brokerage = (entry_turnover + exit_turnover) * rate / 100
     * where turnover = price * quantity * multiplier
     */
    private function closedBrokerageForAccount(TradingAccount $account): float
    {
        if ((float) ($account->brokerage_percent ?? 0) <= 0) {
            return 0.0;
        }

        $rate = (float) $account->brokerage_percent / 100;

        $result = DB::table('positions')
            ->join('instruments', 'positions.instrument_id', '=', 'instruments.id')
            ->join('fills as entry_fills', function ($join) {
                $join->on('entry_fills.instrument_id', '=', 'instruments.id');
            })
            ->where('instruments.trading_account_id', $account->id)
            ->whereNotNull('positions.close_datetime')
            ->selectRaw('
                COALESCE(SUM(entry_fills.price * entry_fills.quantity * instruments.multiplier), 0) AS total_turnover
            ')
            ->value('total_turnover');

        return round((float) $result * $rate, 2);
    }

    /**
     * Used externally when only brokerage total is needed (avoids a second
     * full position load).
     */
    public function totalBrokerageForAccount(TradingAccount $account): float
    {
        // Reuse summarize which is already optimised; grab just total_brokerage.
        return $this->summarize($account)['total_brokerage'];
    }
}

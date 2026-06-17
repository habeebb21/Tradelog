<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\TradingAccount;
use App\Services\AccountMetricsService;

class AccountsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $user->ensureDefaultTradingAccount();
        $metrics = app(AccountMetricsService::class);

        $accounts = TradingAccount::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $activeAccountId = $user->activeTradingAccountId();

        foreach ($accounts as $account) {
            $summary = $metrics->summarize($account);
            $account->total_trades = $summary['total_trades'];
            $account->win_rate = $summary['win_rate'];
            $account->net_pnl = $summary['realized_pnl'];
            $account->balance = $summary['balance'];
            $account->equity = $summary['equity'];
            $account->floating_pnl = $summary['floating_pnl'];
            $account->profit_loss = $summary['realized_pnl'];
            $account->net_profit = $summary['net_profit'];
            $account->net_loss = $summary['net_loss'];
            $account->open_positions_count = $summary['open_positions_count'];
            $account->has_initial_balance = $summary['has_initial_balance'];
            $account->total_brokerage = $summary['total_brokerage'];
            $account->brokerage_percent = $account->brokerage_percent ?? 0;
            // Load deposit/withdrawal history for the adjust balance modal
            $account->balance_entries = \App\Models\Balance::where('trading_account_id', $account->id)
                ->whereIn('type', ['deposit', 'withdrawal'])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();
        }

        return view('accounts.index', compact('accounts', 'activeAccountId'));
    }

    public function activate(Request $request, TradingAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        // Set active account in session
        session(['active_trading_account_id' => $account->id]);

        // Also update default flag in DB to persist
        TradingAccount::where('user_id', auth()->id())->update(['is_default' => false]);
        $account->update(['is_default' => true]);

        return redirect()->route('dashboard')->with('success', "Activated account: {$account->name}");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'broker_name' => 'nullable|string|max:100',
            'market' => 'required|string|max:100',
            'currency' => 'required|string|max:10',
            'starting_balance' => 'nullable|numeric|min:0',
            'brokerage_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $isDefault = $request->boolean('is_default') || !$user->tradingAccounts()->exists();

        if ($isDefault) {
            $user->tradingAccounts()->update(['is_default' => false]);
        }

        $account = $user->tradingAccounts()->create([
            'name' => trim($validated['name']),
            'broker_name' => $validated['broker_name'] ?? null,
            'market' => $validated['market'],
            'currency' => $validated['currency'],
            'starting_balance' => $validated['starting_balance'] ?? null,
            'brokerage_percent' => $validated['brokerage_percent'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'is_default' => $isDefault,
        ]);

        if ($isDefault) {
            session(['active_trading_account_id' => $account->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trading account created successfully.',
            'account' => $account,
        ]);
    }

    public function update(Request $request, TradingAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'broker_name' => 'nullable|string|max:100',
            'market' => 'required|string|max:100',
            'currency' => 'required|string|max:10',
            'starting_balance' => 'nullable|numeric|min:0',
            'brokerage_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');
        $hasSiblingAccounts = $account->user->tradingAccounts()->where('id', '!=', $account->id)->exists();

        if ($isDefault) {
            TradingAccount::where('user_id', auth()->id())->where('id', '!=', $account->id)->update(['is_default' => false]);
        }

        $account->update([
            'name' => trim($validated['name']),
            'broker_name' => $validated['broker_name'] ?? null,
            'market' => $validated['market'],
            'currency' => $validated['currency'],
            'starting_balance' => $validated['starting_balance'] ?? null,
            'brokerage_percent' => $validated['brokerage_percent'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'is_default' => $isDefault || (!$hasSiblingAccounts && !$account->is_default),
        ]);

        if ($account->is_default) {
            session(['active_trading_account_id' => $account->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trading account updated successfully.',
            'account' => $account,
        ]);
    }

    public function storeBalance(Request $request, TradingAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'type'        => 'required|in:deposit,withdrawal',
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $balance = \App\Models\Balance::create([
            'user_id'            => auth()->id(),
            'trading_account_id' => $account->id,
            'type'               => $validated['type'],
            'amount'             => $validated['amount'],
            'date'               => $validated['date'],
            'description'        => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($validated['type']) . ' of ' . number_format($validated['amount'], 2) . ' recorded successfully.',
            'balance' => $balance,
        ]);
    }

    public function destroyBalance(Request $request, \App\Models\Balance $balance)
    {
        if ($balance->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($balance->type === 'initial') {
            return response()->json(['success' => false, 'message' => 'Cannot delete the initial balance entry.'], 422);
        }

        $balance->delete();

        return response()->json(['success' => true, 'message' => 'Entry deleted successfully.']);
    }

    public function destroy(Request $request, TradingAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Prevent deletion if the account has trades/positions
        $hasPositions = Position::whereHas('instrument', function($query) use ($account) {
            $query->where('trading_account_id', $account->id);
        })->exists();

        if ($hasPositions) {
            return response()->json([
                'success' => false,
                'message' => 'Move or delete the trades in this account before deleting it.'
            ], 422);
        }

        $wasDefault = $account->is_default;
        $account->delete();

        if ($wasDefault) {
            $replacement = TradingAccount::where('user_id', auth()->id())->orderBy('id')->first();

            if ($replacement) {
                $replacement->update(['is_default' => true]);
                session(['active_trading_account_id' => $replacement->id]);
            } else {
                session()->forget('active_trading_account_id');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Trading account deleted successfully.'
        ]);
    }
}

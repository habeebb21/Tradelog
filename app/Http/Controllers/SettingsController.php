<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\TradeTag;
use App\Models\Balance;
use App\Models\TradingAccount;

class SettingsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $user->ensureDefaultTradingAccount();

        $tags = TradeTag::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();
        
        $accountId = $user->activeTradingAccountId();
        $activeAccount = $accountId ? $user->tradingAccounts()->where('id', $accountId)->first() : $user->activeTradingAccount();

        $balances = Balance::where('user_id', auth()->id())
            ->when($accountId, function($q) use ($accountId) {
                return $q->where('trading_account_id', $accountId);
            })
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        $hasInitialBalance = $activeAccount?->starting_balance !== null;

        $tradingAccounts = TradingAccount::where('user_id', auth()->id())
            ->withCount('positions')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('settings.index', compact('tags', 'balances', 'hasInitialBalance', 'tradingAccounts'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => strip_tags($validated['name']),
            'email' => $validated['email'],
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = Auth::user();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function storeTag(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        // Check for duplicate tag name for this user
        $exists = TradeTag::where('user_id', auth()->id())
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A tag with this name already exists.'
            ], 422);
        }

        $tag = TradeTag::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'color' => $validated['color'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tag created successfully.',
            'tag' => $tag,
        ]);
    }

    public function updateTag(Request $request, TradeTag $tag)
    {
        // Ensure user owns this tag
        if ($tag->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        // Check for duplicate tag name (excluding current tag)
        $exists = TradeTag::where('user_id', auth()->id())
            ->where('name', $validated['name'])
            ->where('id', '!=', $tag->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A tag with this name already exists.'
            ], 422);
        }

        $tag->update([
            'name' => $validated['name'],
            'color' => $validated['color'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tag updated successfully.',
            'tag' => $tag,
        ]);
    }

    public function destroyTag(TradeTag $tag)
    {
        // Ensure user owns this tag
        if ($tag->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully.'
        ]);
    }

    public function storeBalance(Request $request)
    {
        $accountId = auth()->user()->activeTradingAccountId();
        if (!$accountId) {
            return response()->json([
                'success' => false,
                'message' => 'No active trading account selected. Please select a specific trading account from the Accounts page first.'
            ], 422);
        }

        $validated = $request->validate([
            'type' => 'required|in:initial,deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        // Check if trying to create initial balance when one already exists for this account
        if ($validated['type'] === 'initial') {
            $exists = Balance::where('trading_account_id', $accountId)
                ->where('type', 'initial')
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Initial balance already exists for this trading account.'
                ], 422);
            }
        }

        $balance = Balance::create([
            'user_id' => auth()->id(),
            'trading_account_id' => $accountId,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'description' => $validated['description'],
        ]);

        if ($validated['type'] === 'initial') {
            auth()->user()->tradingAccounts()->where('id', $accountId)->update([
                'starting_balance' => $validated['amount'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Balance entry created successfully.',
            'balance' => $balance,
        ]);
    }

    public function updateBalance(Request $request, Balance $balance)
    {
        if ($balance->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:initial,deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validated['type'] === 'initial') {
            $exists = Balance::where('user_id', auth()->id())
                ->where('trading_account_id', $balance->trading_account_id)
                ->where('type', 'initial')
                ->where('id', '!=', $balance->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Initial balance already exists for this trading account.'
                ], 422);
            }
        }

        $balance->update([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'description' => $validated['description'],
        ]);

        if ($validated['type'] === 'initial') {
            auth()->user()->tradingAccounts()->where('id', $balance->trading_account_id)->update([
                'starting_balance' => $validated['amount'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Balance entry updated successfully.',
            'balance' => $balance,
        ]);
    }

    public function destroyBalance(Balance $balance)
    {
        // Ensure user owns this balance
        if ($balance->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Prevent deletion of initial balance
        if ($balance->type === 'initial') {
            return response()->json([
                'success' => false,
                'message' => 'Initial balance cannot be deleted.'
            ], 422);
        }

        $balance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Balance entry deleted successfully.'
        ]);
    }

    public function storeTradingAccount(Request $request)
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
            $request->session()->put('active_trading_account_id', $account->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trading account created successfully.',
            'account' => $account,
        ]);
    }

    public function updateTradingAccount(Request $request, TradingAccount $account)
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
            'is_default' => $isDefault || (!$hasSiblingAccounts && ! $account->is_default),
        ]);

        if ($account->is_default) {
            $request->session()->put('active_trading_account_id', $account->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trading account updated successfully.',
            'account' => $account,
        ]);
    }

    public function destroyTradingAccount(Request $request, TradingAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        if ($account->positions()->exists()) {
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
                $request->session()->put('active_trading_account_id', $replacement->id);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Trading account deleted successfully.'
        ]);
    }
}

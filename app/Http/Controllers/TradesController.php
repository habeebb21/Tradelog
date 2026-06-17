<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\Instrument;
use App\Models\Fill;
use App\Models\TradeTag;
use App\Models\TradingAccount;
use App\Services\FifoPositionService;
use App\Services\AccountMetricsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradesController extends Controller
{
    protected $fifoService;

    public function __construct(FifoPositionService $fifoService)
    {
        $this->fifoService = $fifoService;
    }

    private function tradingAccounts()
    {
        return TradingAccount::where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function selectedTradingAccountId(?Request $request = null): ?int
    {
        if ($request && $request->filled('account_id') && $request->account_id !== 'all') {
            return (int) $request->account_id;
        }

        $sessionAccountId = session('active_trading_account_id');
        if ($sessionAccountId) {
            if ($sessionAccountId === 'all') {
                return null;
            }
            return (int) $sessionAccountId;
        }

        return auth()->user()?->defaultTradingAccount()?->id;
    }

    private function basePositionQuery(?int $accountId = null)
    {
        $query = Position::with(['instrument.tradingAccount', 'fills', 'tags'])
            ->whereHas('instrument', function($q) use ($accountId) {
                $q->where('user_id', auth()->id());

                if ($accountId) {
                    $q->where('trading_account_id', $accountId);
                }
            });

        return $query;
    }

    private function applyPositionFilters($query, Request $request)
    {
        if ($request->filled('symbol')) {
            $query->whereHas('instrument', function($q) use ($request) {
                $q->where('symbol', 'LIKE', '%' . $request->symbol . '%');
            });
        }

        if ($request->filled('state')) {
            if ($request->state === 'open') {
                $query->whereNull('close_datetime');
            } elseif ($request->state === 'closed') {
                $query->whereNotNull('close_datetime');
            }
        }

        if ($request->filled('asset_type')) {
            $query->whereHas('instrument', function($q) use ($request) {
                $q->where('asset_type', $request->asset_type);
            });
        }

        if ($request->filled('put_call')) {
            $query->whereHas('instrument', function($q) use ($request) {
                $q->where('put_call', $request->put_call);
            });
        }

        if ($request->filled('opened_from')) {
            $query->whereDate('open_datetime', '>=', $request->opened_from);
        }
        if ($request->filled('opened_to')) {
            $query->whereDate('open_datetime', '<=', $request->opened_to);
        }

        if ($request->filled('closed_from')) {
            $query->whereDate('close_datetime', '>=', $request->closed_from);
        }
        if ($request->filled('closed_to')) {
            $query->whereDate('close_datetime', '<=', $request->closed_to);
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', function($q) use ($request) {
                $q->where('trade_tags.id', $request->tag);
            });
        }

        if ($request->filled('pnl_filter')) {
            if ($request->pnl_filter === 'winner') {
                $query->where('realized_pnl', '>', 0);
            } elseif ($request->pnl_filter === 'loser') {
                $query->where('realized_pnl', '<', 0);
            } elseif ($request->pnl_filter === 'breakeven') {
                $query->where('realized_pnl', '=', 0);
            }
        }

        return $query;
    }

    private function summarizePosition(Position $position): array
    {
        $fills = $position->fills->sortBy('datetime');
        $buyFills = $fills->where('side', 'BUY');
        $sellFills = $fills->where('side', 'SELL');

        $buyQuantity = (float) $buyFills->sum('quantity');
        $sellQuantity = (float) $sellFills->sum('quantity');

        $buyValue = (float) $buyFills->sum(fn ($fill) => $fill->quantity * $fill->price);
        $sellValue = (float) $sellFills->sum(fn ($fill) => $fill->quantity * $fill->price);

        $buyPrice = $buyQuantity > 0 ? $buyValue / $buyQuantity : null;
        $sellPrice = $sellQuantity > 0 ? $sellValue / $sellQuantity : null;

        return [
            'buy_quantity' => $buyQuantity ?: null,
            'buy_price' => $buyPrice,
            'sell_quantity' => $sellQuantity ?: null,
            'sell_price' => $sellPrice,
            'charges' => (float) $fills->sum('fees'),
            'pnl' => $position->realized_pnl,
        ];
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function xlsxEscape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return str_replace('&apos;', '&#39;', $escaped);
    }

    private function formatExcelNumeric($value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        $formatted = number_format((float) $value, 8, '.', '');
        $formatted = rtrim($formatted, '0');
        return rtrim($formatted, '.');
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 30);

        if ($request->filled('account_id')) {
            if ($request->account_id === 'all') {
                session(['active_trading_account_id' => 'all']);
            } else {
                session(['active_trading_account_id' => (int) $request->account_id]);
            }
        }

        $accountId = $this->selectedTradingAccountId($request);
        $selectedAccount = $accountId
            ? TradingAccount::where('user_id', auth()->id())->where('id', $accountId)->first()
            : null;
        $accountSummary = $selectedAccount
            ? app(AccountMetricsService::class)->summarize($selectedAccount)
            : null;

        $query = $this->basePositionQuery(
            $request->filled('account_id') && $request->account_id === 'all' ? null : $accountId
        );
        $this->applyPositionFilters($query, $request);

        $sortBy = $request->get('sort_by', 'close_datetime');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortBy === 'symbol') {
            $query->join('instruments', 'positions.instrument_id', '=', 'instruments.id')
                ->select('positions.*')
                ->orderBy('instruments.symbol', $sortOrder);
        } elseif ($sortBy === 'pnl') {
            $query->orderBy('realized_pnl', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        if ($perPage === 'all') {
            $positions = $query->get();
            // Create a custom paginator for "all" results
            $positions = new \Illuminate\Pagination\LengthAwarePaginator(
                $positions,
                $positions->count(),
                $positions->count(),
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $positions = $query->paginate((int)$perPage)->appends($request->except('page'));
        }

        $visiblePositions = $perPage === 'all' ? collect($positions->items()) : $positions->getCollection();
        $openPositionsCount = $visiblePositions->where('close_datetime', null)->count();
        $closedPositionsCount = $visiblePositions->where('close_datetime', '!=', null)->count();
        $totalFloatingPnl = round((float) $visiblePositions->sum(function (Position $position) {
            if ($position->isClosed()) return 0;
            if ($position->isMarked()) return $position->markRealizedPnL() ?? 0;
            return $position->floatingPnL() ?? 0;
        }), 2);
        $totalRealizedPnl = round((float) $visiblePositions->sum(fn (Position $position) => $position->isClosed() ? ($position->realized_pnl ?? 0) : 0), 2);
        $totalBrokerage = round((float) $visiblePositions->sum(fn (Position $position) => $position->totalBrokerageAmount()), 2);
        $grandTotal = round($totalFloatingPnl + $totalRealizedPnl - $totalBrokerage, 2);
        $tradeSummary = [
            'open_positions_count' => $openPositionsCount,
            'closed_positions_count' => $closedPositionsCount,
            'total_floating_pnl' => $totalFloatingPnl,
            'total_realized_pnl' => $totalRealizedPnl,
            'total_brokerage' => $totalBrokerage,
            'grand_total' => $grandTotal,
        ];

        $accounts = $this->tradingAccounts();
        $userTags = TradeTag::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        return view('trades', compact('positions', 'userTags', 'accounts', 'accountId', 'tradeSummary', 'accountSummary'));
    }

    public function create()
    {
        $user = auth()->user();
        $user->ensureDefaultTradingAccount();

        // Get all tags for the current user
        $userTags = TradeTag::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        $accounts = $this->tradingAccounts();
        $activeAccount = session('active_trading_account_id');
        $selectedAccountId = ($activeAccount && $activeAccount !== 'all')
            ? $activeAccount
            : ($accounts->firstWhere('is_default', true)?->id ?? $accounts->first()?->id);
        if ($activeAccount && $activeAccount !== 'all') {
            session(['active_trading_account_id' => $selectedAccountId]);
        }
        
        // Find the "Untagged" tag ID for default selection
        $defaultTagId = $userTags->where('name', 'Untagged')->first()?->id;

        return view('trades.create', compact('userTags', 'defaultTagId', 'user', 'accounts', 'selectedAccountId'));
    }

    public function show(Position $position)
    {
        // Ensure user owns this position
        if ($position->instrument->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        // Load the instrument and all fills for this instrument
        $position->load(['instrument.fills' => function($query) use ($position) {
            $query->orderBy('datetime', 'asc');
        }, 'tags']);
        
        // Get all available tags for the user
        $availableTags = TradeTag::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        return view('trades.show', compact('position', 'availableTags'));
    }

    public function edit(Position $position)
    {
        // Ensure user owns this position
        if ($position->instrument->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        // Load related fills
        $position->load(['instrument.fills' => function($q) {
            $q->orderBy('datetime', 'asc');
        }, 'tags']);

        $availableTags = TradeTag::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        // Prepare data for edit form (pre‑fill values)
        $instrument = $position->instrument;
        $buyFill = $instrument->fills()->where('side', 'BUY')->first();
        $sellFill = $instrument->fills()->where('side', 'SELL')->first();

        return view('trades.edit', compact('position', 'instrument', 'buyFill', 'sellFill', 'availableTags'));
    }

    public function updateMarketPrice(Request $request, Position $position)
    {
        if ($position->instrument->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'current_price' => 'nullable|numeric|min:0',
            'mark_mode'     => 'nullable|boolean',
        ]);

        $price    = $request->filled('current_price') ? (float) $validated['current_price'] : null;
        $markMode = $price !== null ? (bool) ($request->input('mark_mode', false)) : false;

        $position->instrument->update([
            'current_price' => $price,
            'mark_mode'     => $markMode,
        ]);

        return back()->with('success', 'Market price updated successfully.');
    }

    public function update(Request $request, Position $position)
    {
        // Ensure user owns this position
        if ($position->instrument->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->has('edit_trade')) {
            $validated = $request->validate([
                'symbol' => 'required|string|max:50',
                'quantity' => 'required|numeric|min:0.01',
                'trade_side' => 'required|in:BUY,SELL',
                'entry_price' => 'required|numeric|min:0',
                'opened_date' => 'required|date',
                'exit_price' => 'nullable|numeric|min:0',
                'closed_date' => 'nullable|date|after_or_equal:opened_date',
                'option_type' => 'nullable|in:CE,PE',
                'strike_price' => 'nullable|numeric|min:0',
                'expiration_date' => 'nullable|date',
                'multiplier' => 'nullable|integer|min:1',
                'current_price' => 'nullable|numeric|min:0',
            ]);

            // Validate that if exit_price is provided, closed_date must also be provided
            if ($request->filled('exit_price') && !$request->filled('closed_date')) {
                return back()->with('error', 'Closed Date is required when Exit Price is provided.');
            }

            try {
                DB::beginTransaction();

                $instrument = $position->instrument;
                if ($validated['trade_side'] !== $position->tradeSide()) {
                    $instrument->fills()->delete();
                }
                $instrument->update([
                    'symbol' => strtoupper($validated['symbol']),
                    'underlying_symbol' => strtoupper($validated['symbol']),
                    'put_call' => $instrument->isOption() && $request->filled('option_type') ? ($validated['option_type'] === 'CE' ? 'C' : 'P') : $instrument->put_call,
                    'strike' => $validated['strike_price'] ?? $instrument->strike,
                    'expiry' => $validated['expiration_date'] ?? $instrument->expiry,
                    'multiplier' => $validated['multiplier'] ?? $instrument->multiplier,
                    'current_price' => $request->filled('current_price') ? (float) $validated['current_price'] : $instrument->current_price,
                ]);

                $multiplier = $instrument->multiplier ?? 1;
                $qty = (float) $validated['quantity'];
                $entryPrice = (float) $validated['entry_price'];
                $direction = $validated['trade_side'] === 'SELL' ? -1 : 1;
                $brokerageRate = (float) ($instrument->tradingAccount?->brokerage_percent ?? 0);

                $entrySide = $validated['trade_side'];
                $entryFill = $instrument->fills()->where('side', $entrySide)->first();
                if ($entryFill) {
                    $entryFill->update([
                        'datetime' => $validated['opened_date'],
                        'quantity' => $qty,
                        'price'    => $entryPrice,
                        'fees'     => 0,
                    ]);
                } else {
                    Fill::create([
                        'instrument_id' => $instrument->id,
                        'datetime'      => $validated['opened_date'],
                        'side'          => $entrySide,
                        'quantity'      => $qty,
                        'price'         => $entryPrice,
                        'fees'          => 0,
                    ]);
                }

                $costBasis = $entryPrice;

                // Handle Exit/Close
                $exitPrice  = $request->filled('exit_price') ? (float) $validated['exit_price'] : null;
                $closedDate = $request->filled('closed_date') ? $validated['closed_date'] : null;
                $exitSide   = $validated['trade_side'] === 'BUY' ? 'SELL' : 'BUY';
                $exitFill   = $instrument->fills()->where('side', $exitSide)->first();

                if ($exitPrice !== null && $closedDate !== null) {
                    if ($exitFill) {
                        $exitFill->update([
                            'datetime' => $closedDate,
                            'quantity' => $qty,
                            'price'    => $exitPrice,
                            'fees'     => 0,
                        ]);
                    } else {
                        Fill::create([
                            'instrument_id' => $instrument->id,
                            'datetime'      => $closedDate,
                            'side'          => $exitSide,
                            'quantity'      => $qty,
                            'price'         => $exitPrice,
                            'fees'          => 0,
                        ]);
                    }
                    // Realized P&L = gross P&L − entry brokerage − exit brokerage
                    // Both sides use the same account brokerage_percent
                    $entryTurnover  = $entryPrice * $qty * $multiplier;
                    $exitTurnover   = $exitPrice  * $qty * $multiplier;
                    $entryBrokerage = round($entryTurnover * ($brokerageRate / 100), 2);
                    $exitBrokerage  = round($exitTurnover  * ($brokerageRate / 100), 2);
                    $grossPnl       = ($exitPrice - $entryPrice) * $qty * $multiplier * $direction;
                    $realizedPnl    = round($grossPnl - $entryBrokerage - $exitBrokerage, 2);
                } else {
                    if ($exitFill) {
                        $exitFill->delete();
                    }
                    $closedDate = null;
                    $realizedPnl = null;
                }

                $position->update([
                    'open_datetime' => $validated['opened_date'],
                    'quantity' => $qty,
                    'cost_basis' => $costBasis,
                    'close_datetime' => $closedDate,
                    'realized_pnl' => $realizedPnl,
                ]);

                DB::commit();
                return back()->with('success', 'Trade details updated successfully.');
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'Failed to update trade: ' . $e->getMessage());
            }
        }

        if ($request->has('close_trade')) {
            $validated = $request->validate([
                'exit_price'  => 'required|numeric|min:0',
                'closed_date' => 'required|date|after_or_equal:' . $position->open_datetime->toIso8601String(),
            ]);

            try {
                DB::beginTransaction();

                $instrument     = $position->instrument;
                $multiplier     = $instrument->multiplier ?? 1;
                $qty            = (float) $position->quantity;
                $costBasis      = (float) $position->cost_basis;
                $direction      = $position->tradeSide() === 'SELL' ? -1 : 1;
                $exitPrice      = (float) $validated['exit_price'];
                $brokerageRate  = (float) ($instrument->tradingAccount?->brokerage_percent ?? 0);
                $exitSide       = $position->tradeSide() === 'BUY' ? 'SELL' : 'BUY';

                $exitFill = $instrument->fills()->where('side', $exitSide)->first();
                if ($exitFill) {
                    $exitFill->update([
                        'datetime' => $validated['closed_date'],
                        'quantity' => $qty,
                        'price'    => $exitPrice,
                        'fees'     => 0,
                    ]);
                } else {
                    Fill::create([
                        'instrument_id' => $instrument->id,
                        'datetime'      => $validated['closed_date'],
                        'side'          => $exitSide,
                        'quantity'      => $qty,
                        'price'         => $exitPrice,
                        'fees'          => 0,
                    ]);
                }

                // Realized P&L = gross P&L − entry brokerage − exit brokerage
                $entryTurnover  = $costBasis * $qty * $multiplier;
                $exitTurnover   = $exitPrice  * $qty * $multiplier;
                $entryBrokerage = round($entryTurnover * ($brokerageRate / 100), 2);
                $exitBrokerage  = round($exitTurnover  * ($brokerageRate / 100), 2);
                $grossPnl       = ($exitPrice - $costBasis) * $qty * $multiplier * $direction;
                $realizedPnl    = round($grossPnl - $entryBrokerage - $exitBrokerage, 2);

                $position->update([
                    'close_datetime' => $validated['closed_date'],
                    'realized_pnl' => $realizedPnl,
                ]);

                DB::commit();
                return back()->with('success', 'Position closed successfully.');
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'Failed to close position: ' . $e->getMessage());
            }
        }

        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $position->update([
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', 'Notes updated successfully.');
    }

    public function storeManual(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:trading_accounts,id',
            'asset_type' => 'required|in:stock,option,future',
            'trade_side' => 'required|in:BUY,SELL',
            'symbol' => 'required|string|max:50',
            'quantity' => 'required|numeric|min:0.01',
            'opened_date' => 'required|date',
            'closed_date' => 'nullable|date|after_or_equal:opened_date',
            'entry_price' => 'required|numeric|min:0',
            'exit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:trade_tags,id',
            // Option/Future specific fields
            'option_type' => 'nullable|string|max:2',
            'strike_price' => 'nullable|numeric|min:0',
            'expiration_date' => 'nullable|date',
            'multiplier' => 'nullable|integer|min:1',
            'future_expiration_date' => 'nullable|date',
            'future_multiplier' => 'nullable|integer|min:1',
        ]);

        if ($validated['asset_type'] === 'option') {
            if (!in_array($request->input('option_type'), ['CE', 'PE'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Option type is required for option trades.'
                ], 422);
            }

            if (!$request->filled('strike_price')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Strike price is required for option trades.'
                ], 422);
            }

            if (!$request->filled('expiration_date')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expiration date is required for option trades.'
                ], 422);
            }

            if (!$request->filled('multiplier')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lot size is required for option trades.'
                ], 422);
            }
        }

        if ($validated['asset_type'] === 'future') {
            if (!$request->filled('future_expiration_date')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract expiry is required for futures trades.'
                ], 422);
            }

            if (!$request->filled('future_multiplier')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lot size is required for futures trades.'
                ], 422);
            }
        }

        // Validate that if exit_price is provided, closed_date must also be provided
        if ($request->filled('exit_price') && !$request->filled('closed_date')) {
            return response()->json([
                'success' => false,
                'message' => 'Closed Date is required when Exit Price is provided.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Map asset type to database format
            $assetTypeMap = [
                'stock' => 'STK',
                'option' => 'OPT',
                'future' => 'FUT',
            ];

            // Create or find instrument
            $instrumentData = [
                'user_id' => auth()->id(),
                'trading_account_id' => $validated['account_id'],
                'symbol' => strtoupper($validated['symbol']),
                'underlying_symbol' => strtoupper($validated['symbol']),
                'asset_type' => $assetTypeMap[$validated['asset_type']],
                'currency' => 'INR',
                'current_price' => null,
            ];

            $entryPrice = (float) $validated['entry_price'];
            $quantity = (float) $validated['quantity'];
            $openSide = $validated['trade_side'];
            $closeSide = $openSide === 'BUY' ? 'SELL' : 'BUY';
            $direction = $openSide === 'SELL' ? -1 : 1;
            $optionExpiry = $validated['expiration_date'] ?? null;
            $futureExpiry = $request->input('future_expiration_date') ?: $optionExpiry;
            $optionMultiplier = $validated['multiplier'] ?? 50;
            $futureMultiplier = $request->input('future_multiplier') ?: ($validated['multiplier'] ?? 1);

            // Add derivative-specific fields
            if ($validated['asset_type'] === 'option') {
                $instrumentData['put_call'] = $validated['option_type'] === 'CE' ? 'C' : 'P';
                $instrumentData['strike'] = $validated['strike_price'];
                $instrumentData['expiry'] = $optionExpiry;
                $instrumentData['multiplier'] = $optionMultiplier;
            } elseif ($validated['asset_type'] === 'future') {
                $instrumentData['put_call'] = null;
                $instrumentData['strike'] = null;
                $instrumentData['expiry'] = $futureExpiry;
                $instrumentData['multiplier'] = $futureMultiplier;
            } else {
                $instrumentData['put_call'] = null;
                $instrumentData['strike'] = null;
                $instrumentData['expiry'] = null;
                $instrumentData['multiplier'] = 1;
            }

            $instrument = Instrument::create($instrumentData);

            // Create opening fill using the selected side
            $openFill = Fill::create([
                'instrument_id' => $instrument->id,
                'datetime' => $validated['opened_date'],
                'side' => $openSide,
                'quantity' => $quantity,
                'price' => $entryPrice,
                'fees' => 0,
            ]);

            // Cost basis is stored per contract/share
            $multiplier = $instrumentData['multiplier'];
            $costBasis = $entryPrice;

            // Create position
            $positionData = [
                'instrument_id' => $instrument->id,
                'open_datetime' => $validated['opened_date'],
                'quantity' => $validated['quantity'],
                'cost_basis' => $costBasis,
                'notes' => $validated['notes'] ?? null,
            ];

            // If position is closed, create closing fill and calculate P&L
            if ($request->filled('exit_price') && $request->filled('closed_date')) {
                $closeFill = Fill::create([
                    'instrument_id' => $instrument->id,
                    'datetime' => $validated['closed_date'],
                    'side' => $closeSide,
                    'quantity' => $quantity,
                    'price' => (float) $validated['exit_price'],
                    'fees' => 0.0,
                ]);

                // Calculate realized P&L using account brokerage_percent
                $exitPrice     = (float) $validated['exit_price'];
                $account       = \App\Models\TradingAccount::find($validated['account_id']);
                $brokerageRate = (float) ($account?->brokerage_percent ?? 0);
                $entryTurnover = $entryPrice * $quantity * $multiplier;
                $exitTurnover  = $exitPrice  * $quantity * $multiplier;
                $entryBrokerage = round($entryTurnover * ($brokerageRate / 100), 2);
                $exitBrokerage  = round($exitTurnover  * ($brokerageRate / 100), 2);
                $grossPnl       = ($exitPrice - $entryPrice) * $direction * $quantity * $multiplier;
                $realizedPnl    = round($grossPnl - $entryBrokerage - $exitBrokerage, 2);

                $positionData['close_datetime'] = $validated['closed_date'];
                $positionData['realized_pnl'] = $realizedPnl;
            }

            $position = Position::create($positionData);

            // Attach selected tags to new position (or Untagged if none selected)
            if ($request->filled('tag_ids') && is_array($validated['tag_ids']) && count($validated['tag_ids']) > 0) {
                // Verify all tags belong to the current user before attaching
                $userTagIds = TradeTag::where('user_id', auth()->id())
                    ->whereIn('id', $validated['tag_ids'])
                    ->pluck('id')
                    ->toArray();
                
                if (!empty($userTagIds)) {
                    $position->tags()->attach($userTagIds);
                }
            } else {
                // Default to Untagged if no tags selected
                $untaggedTag = TradeTag::where('user_id', auth()->id())
                    ->where('name', 'Untagged')
                    ->first();
                
                if ($untaggedTag) {
                    $position->tags()->attach($untaggedTag->id);
                }
            }

            DB::commit();

            session(['active_trading_account_id' => $validated['account_id']]);

            return response()->json([
                'success' => true,
                'message' => 'Trade saved successfully!',
                'position_id' => $position->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save trade: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'broker' => 'required|string',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'account_id' => 'required|exists:trading_accounts,id',
        ]);

        try {
            $broker = $request->input('broker');
            $file = $request->file('csv_file');
            
            \Log::info('CSV Upload started', ['broker' => $broker, 'file' => $file->getClientOriginalName()]);
            
            // Parse CSV based on broker
            if ($broker === 'interactive_broker') {
                $result = $this->parseInteractiveBrokerCSV($file, (int) $request->input('account_id'));
                
                \Log::info('CSV Upload completed', $result);
                
                return redirect()->route('trades', ['account_id' => $request->input('account_id')])->with('success', 
                    "Successfully imported {$result['fills']} fills for {$result['instruments']} instruments. Created {$result['positions']} positions.");
            }
            
            return back()->with('error', 'Unsupported broker format.');
            
        } catch (\Exception $e) {
            \Log::error('CSV Upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Error processing CSV: ' . $e->getMessage());
        }
    }

    private function parseInteractiveBrokerCSV($file, int $accountId)
    {
        $csvData = array_map('str_getcsv', file($file->getPathname()));
        $headers = array_shift($csvData); // Remove header row
        
        $fillsCount = 0;
        $instrumentsCreated = 0;
        $processedInstruments = collect();

        DB::beginTransaction();
        
        try {
            foreach ($csvData as $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Create associative array from CSV row
                $data = array_combine($headers, $row);
                
                // Skip if Symbol is empty
                if (empty($data['Symbol'])) {
                    continue;
                }
                
                // Sanitize and prepare instrument data
                $assetType = $data['AssetClass'] === 'STK' ? 'STK' : 'OPT';
                
                // Clean symbol - remove extra spaces
                $cleanSymbol = preg_replace('/\s+/', '', trim($data['Symbol']));
                
                $instrumentData = [
                    'user_id' => auth()->id(),
                    'trading_account_id' => $accountId,
                    'symbol' => $cleanSymbol,
                    'underlying_symbol' => !empty($data['UnderlyingSymbol']) ? trim($data['UnderlyingSymbol']) : null,
                    'asset_type' => $assetType,
                    'currency' => 'INR',
                ];
                
                // Add option-specific fields
                if ($assetType === 'OPT') {
                    // For expiry, handle empty or invalid dates
                    $expiryDate = null;
                    if (!empty($data['Expiry'])) {
                        try {
                            $expiryDate = date('Y-m-d', strtotime($data['Expiry']));
                        } catch (\Exception $e) {
                            \Log::warning('Invalid expiry date', ['expiry' => $data['Expiry'], 'symbol' => $cleanSymbol]);
                        }
                    }
                    
                    $instrumentData['expiry'] = $expiryDate;
                    $instrumentData['strike'] = !empty($data['Strike']) ? (float)$data['Strike'] : null;
                    
                    $putCallInput = !empty($data['Put/Call']) ? strtoupper(trim($data['Put/Call'])) : null;
                    $putCallMapped = null;
                    if ($putCallInput === 'CALL' || $putCallInput === 'CE' || $putCallInput === 'C') {
                        $putCallMapped = 'C';
                    } elseif ($putCallInput === 'PUT' || $putCallInput === 'PE' || $putCallInput === 'P') {
                        $putCallMapped = 'P';
                    }
                    $instrumentData['put_call'] = $putCallMapped;
                    
                    $instrumentData['multiplier'] = !empty($data['Multiplier']) ? (int)$data['Multiplier'] : 1;
                } else {
                    $instrumentData['expiry'] = null;
                    $instrumentData['strike'] = null;
                    $instrumentData['put_call'] = null;
                    $instrumentData['multiplier'] = 1;
                }
                
                // Find or create instrument - use whereDate for proper date comparison
                $instrument = Instrument::where('user_id', auth()->id())
                    ->where('trading_account_id', $accountId)
                    ->where('symbol', $instrumentData['symbol'])
                    ->where('asset_type', $instrumentData['asset_type'])
                    ->where(function($query) use ($instrumentData) {
                        if ($instrumentData['expiry'] === null) {
                            $query->whereNull('expiry');
                        } else {
                            $query->whereDate('expiry', $instrumentData['expiry']);
                        }
                    })
                    ->where(function($query) use ($instrumentData) {
                        if ($instrumentData['strike'] === null) {
                            $query->whereNull('strike');
                        } else {
                            $query->where('strike', $instrumentData['strike']);
                        }
                    })
                    ->where(function($query) use ($instrumentData) {
                        if ($instrumentData['put_call'] === null) {
                            $query->whereNull('put_call');
                        } else {
                            $query->where('put_call', $instrumentData['put_call']);
                        }
                    })
                    ->first();
                
                if (!$instrument) {
                    $instrument = Instrument::create($instrumentData);
                    $instrumentsCreated++;
                }
                if (!$instrument) {
                    $instrument = Instrument::create($instrumentData);
                    $instrumentsCreated++;
                }
                
                // Track this instrument for FIFO processing
                if (!$processedInstruments->contains($instrument->id)) {
                    $processedInstruments->push($instrument->id);
                }
                
                // Check for duplicate fills by exec_id
                $execId = trim($data['TradeID']);
                if (Fill::where('exec_id', $execId)->exists()) {
                    \Log::info('Skipping duplicate fill', ['exec_id' => $execId]);
                    continue; // Skip duplicate
                }
                
                // Parse datetime with error handling
                $fillDatetime = null;
                try {
                    $fillDatetime = date('Y-m-d H:i:s', strtotime($data['DateTime']));
                } catch (\Exception $e) {
                    \Log::warning('Invalid datetime', ['datetime' => $data['DateTime'], 'symbol' => $cleanSymbol]);
                    continue; // Skip this fill if datetime is invalid
                }
                
                // Create fill record
                Fill::create([
                    'instrument_id' => $instrument->id,
                    'datetime' => $fillDatetime,
                    'side' => strtoupper(trim($data['Buy/Sell'])) === 'BUY' ? 'BUY' : 'SELL',
                    'quantity' => abs((float)$data['Quantity']),
                    'price' => (float)$data['TradePrice'],
                    'fees' => 0.0,
                    'order_id' => !empty($data['TradeID']) ? trim($data['TradeID']) : null,
                    'exec_id' => $execId,
                ]);
                
                $fillsCount++;
            }
            
            // Process FIFO for all affected instruments
            foreach ($processedInstruments as $instrumentId) {
                $instrument = Instrument::find($instrumentId);
                $this->fifoService->processInstrument($instrument);
            }
            
            $positionsCount = Position::count();
            
            DB::commit();
            
            return [
                'fills' => $fillsCount,
                'instruments' => $instrumentsCreated,
                'positions' => $positionsCount,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(Position $position)
    {
        // Ensure user owns this position
        if ($position->instrument->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            DB::beginTransaction();
            
            $instrumentId = $position->instrument_id;
            
            // Delete the position
            $position->delete();
            
            // Check if this instrument has any remaining positions or fills
            $instrument = Instrument::find($instrumentId);
            if ($instrument) {
                $hasPositions = $instrument->positions()->exists();
                $hasFills = $instrument->fills()->exists();
                
                // If no positions or fills remain, delete the instrument (cascade deletes all)
                if (!$hasPositions && !$hasFills) {
                    $instrument->delete();
                }
            }
            
            DB::commit();
            
            return redirect()->route('trades')->with('success', 'Position deleted successfully.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error deleting position: ' . $e->getMessage());
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'position_ids' => 'required|array',
            'position_ids.*' => 'exists:positions,id',
        ]);
        
        // Ensure user owns all selected positions
        $invalidPositions = Position::whereIn('id', $request->position_ids)
            ->whereHas('instrument', function($query) {
                $query->where('user_id', '!=', auth()->id());
            })
            ->exists();
            
        if ($invalidPositions) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();
            
            $positionIds = $request->input('position_ids');
            
            // Get all affected instruments before deleting positions
            $affectedInstruments = Position::whereIn('id', $positionIds)
                ->pluck('instrument_id')
                ->unique();
            
            // Delete all selected positions
            $deletedCount = Position::whereIn('id', $positionIds)->delete();
            
            // Clean up orphaned instruments
            foreach ($affectedInstruments as $instrumentId) {
                $instrument = Instrument::find($instrumentId);
                if ($instrument) {
                    $hasPositions = $instrument->positions()->exists();
                    $hasFills = $instrument->fills()->exists();
                    
                    if (!$hasPositions && !$hasFills) {
                        $instrument->delete();
                    }
                }
            }
            
            DB::commit();
            
            return redirect()->route('trades')->with('success', "Successfully deleted {$deletedCount} position(s).");
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error deleting positions: ' . $e->getMessage());
        }
    }

    public function exportPdf(Request $request)
    {
        $accountId = $request->filled('account_id') && $request->account_id !== 'all'
            ? (int) $request->account_id
            : $this->selectedTradingAccountId($request);

        $query = $this->basePositionQuery(
            $request->filled('account_id') && $request->account_id === 'all' ? null : $accountId
        );
        $this->applyPositionFilters($query, $request);

        $positions = $query->with(['instrument.tradingAccount', 'fills', 'tags'])
            ->orderBy('open_datetime', 'desc')
            ->get();

        $accountName = $accountId
            ? TradingAccount::where('user_id', auth()->id())->where('id', $accountId)->first()?->name ?? 'All Accounts'
            : 'All Accounts';

        // Summary totals
        $totalEntryBrok   = round($positions->sum(fn ($p) => $p->entryBrokerageAmount()), 2);
        $totalExitBrok    = round($positions->sum(fn ($p) => $p->isClosed() ? $p->exitBrokerageAmount() : ($p->markPrice() !== null ? $p->exitBrokerageAmount($p->markPrice()) : 0)), 2);
        $totalBrok        = round($totalEntryBrok + $totalExitBrok, 2);
        $totalFloating    = round($positions->sum(fn ($p) => !$p->isClosed() && !$p->isMarked() ? ($p->floatingPnL() ?? 0) : 0), 2);
        $totalRealized    = round($positions->sum(fn ($p) => $p->isClosed() ? ($p->realized_pnl ?? 0) : ($p->isMarked() ? ($p->markRealizedPnL() ?? 0) : 0)), 2);

        $html  = '<!DOCTYPE html>';
        $html .= '<html><head><meta charset="UTF-8">';
        $html .= '<title>Tradelog — Trade Export</title>';
        $html .= '<style>';
        $html .= '*{margin:0;padding:0;box-sizing:border-box;}';
        $html .= 'body{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#1a1a1a;background:#fff;}';
        $html .= '.header{padding:18px 24px 12px;border-bottom:2px solid #ea580c;display:flex;justify-content:space-between;align-items:flex-end;}';
        $html .= '.header h1{font-size:20px;font-weight:700;color:#ea580c;letter-spacing:.5px;}';
        $html .= '.header .meta{font-size:10px;color:#555;text-align:right;line-height:1.6;}';
        $html .= '.summary{display:flex;gap:0;border-bottom:1px solid #e5e7eb;background:#f9fafb;}';
        $html .= '.summary-cell{flex:1;padding:10px 14px;border-right:1px solid #e5e7eb;}';
        $html .= '.summary-cell:last-child{border-right:none;}';
        $html .= '.summary-cell .label{font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;font-weight:600;}';
        $html .= '.summary-cell .value{font-size:13px;font-weight:700;margin-top:2px;}';
        $html .= '.pos{color:#16a34a;}.neg{color:#dc2626;}.neutral{color:#374151;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-top:0;}';
        $html .= 'thead tr{background:#1e293b;color:#fff;}';
        $html .= 'thead th{padding:7px 8px;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;}';
        $html .= 'thead th.right{text-align:right;}';
        $html .= 'tbody tr{border-bottom:1px solid #f3f4f6;}';
        $html .= 'tbody tr:nth-child(even){background:#f9fafb;}';
        $html .= 'tbody td{padding:6px 8px;vertical-align:middle;white-space:nowrap;}';
        $html .= 'tbody td.right{text-align:right;}';
        $html .= '.badge{display:inline-block;padding:2px 7px;border-radius:9999px;font-size:9px;font-weight:700;}';
        $html .= '.badge-buy{background:#dcfce7;color:#15803d;}';
        $html .= '.badge-sell{background:#fee2e2;color:#b91c1c;}';
        $html .= '.badge-open{background:#fef9c3;color:#a16207;}';
        $html .= '.badge-closed{background:#f3f4f6;color:#374151;}';
        $html .= '.badge-mark{background:#ede9fe;color:#6d28d9;}';
        $html .= '.badge-fo{background:#e0f2fe;color:#0369a1;}';
        $html .= '.badge-eq{background:#dcfce7;color:#15803d;}';
        $html .= 'tfoot tr{background:#1e293b;color:#fff;font-weight:700;}';
        $html .= 'tfoot td{padding:7px 8px;}';
        $html .= 'tfoot td.right{text-align:right;}';
        $html .= '@media print{@page{size:A4 landscape;margin:8mm;}body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}';
        $html .= '</style></head>';
        $html .= '<body>';

        // Header
        $html .= '<div class="header">';
        $html .= '<div><h1>Tradelog</h1><div style="font-size:11px;color:#555;margin-top:3px;">Trade Export — ' . e($accountName) . '</div></div>';
        $html .= '<div class="meta">Generated: ' . now()->format('d M Y, h:i A') . '<br>Total Trades: ' . $positions->count() . '</div>';
        $html .= '</div>';

        // Summary bar
        $fClass = $totalFloating >= 0 ? 'pos' : 'neg';
        $rClass = $totalRealized >= 0 ? 'pos' : 'neg';
        $html .= '<div class="summary">';
        $html .= '<div class="summary-cell"><div class="label">Entry Brokerage</div><div class="value neg">-₹' . number_format($totalEntryBrok, 2) . '</div></div>';
        $html .= '<div class="summary-cell"><div class="label">Exit Brokerage</div><div class="value neg">-₹' . number_format($totalExitBrok, 2) . '</div></div>';
        $html .= '<div class="summary-cell"><div class="label">Total Brokerage</div><div class="value neg">-₹' . number_format($totalBrok, 2) . '</div></div>';
        $html .= '<div class="summary-cell"><div class="label">Floating P&amp;L</div><div class="value ' . $fClass . '">' . ($totalFloating >= 0 ? '+' : '') . '₹' . number_format($totalFloating, 2) . '</div></div>';
        $html .= '<div class="summary-cell"><div class="label">Realized P&amp;L</div><div class="value ' . $rClass . '">' . ($totalRealized >= 0 ? '+' : '') . '₹' . number_format($totalRealized, 2) . '</div></div>';
        $html .= '</div>';

        // Table
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>#</th><th>Script</th><th>Seg</th><th>Side</th>';
        $html .= '<th class="right">Lots</th><th class="right">Lot Size</th><th class="right">Total Qty</th>';
        $html .= '<th class="right">Entry ₹</th><th class="right">Exit ₹</th><th class="right">Mark ₹</th>';
        $html .= '<th class="right">Brok%</th><th class="right">Entry Brok</th><th class="right">Exit Brok</th><th class="right">Total Brok</th>';
        $html .= '<th class="right">Floating P&amp;L</th><th class="right">Realized P&amp;L</th>';
        $html .= '<th>Opened</th><th>Closed</th><th>Status</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($positions as $i => $p) {
            $inst     = $p->instrument;
            $mult     = (int) ($inst->multiplier ?? 1);
            $qty      = (float) $p->quantity;
            $totalQty = $qty * $mult;
            $entry    = $p->entryAveragePrice() ?? (float) $p->cost_basis;
            $exit     = $p->exitAveragePrice();
            $mark     = $p->isOpen() && $p->markPrice() !== null ? $p->markPrice() : null;
            $side     = $p->tradeSide();
            $seg      = $inst->asset_type === 'STK' ? 'EQ' : 'F&O';
            $entryBrok = $p->entryBrokerageAmount();
            $exitBrok  = $p->isClosed() ? $p->exitBrokerageAmount() : ($mark !== null ? $p->exitBrokerageAmount($mark) : 0);
            $floating  = !$p->isClosed() && !$p->isMarked() ? $p->floatingPnL() : null;
            $realized  = $p->isClosed() ? (float) $p->realized_pnl : ($p->isMarked() ? $p->markRealizedPnL() : null);
            $status    = $p->isClosed() ? 'Closed' : ($p->isMarked() ? 'Mark' : 'Open');

            $fStr = $floating !== null ? (($floating >= 0 ? '+' : '') . '₹' . number_format($floating, 2)) : '—';
            $rStr = $realized !== null ? (($realized >= 0 ? '+' : '') . '₹' . number_format($realized, 2)) : '—';
            $fTd  = $floating !== null ? ($floating >= 0 ? 'pos' : 'neg') : 'neutral';
            $rTd  = $realized !== null ? ($realized >= 0 ? 'pos' : 'neg') : 'neutral';

            $html .= '<tr>';
            $html .= '<td>' . ($i + 1) . '</td>';
            $html .= '<td><strong>' . e($inst->symbol) . '</strong></td>';
            $html .= '<td><span class="badge ' . ($seg === 'EQ' ? 'badge-eq' : 'badge-fo') . '">' . $seg . '</span></td>';
            $html .= '<td><span class="badge ' . ($side === 'BUY' ? 'badge-buy' : 'badge-sell') . '">' . $side . '</span></td>';
            $html .= '<td class="right">' . number_format($qty, 0) . '</td>';
            $html .= '<td class="right">' . number_format($mult, 0) . '</td>';
            $html .= '<td class="right">' . number_format($totalQty, 0) . '</td>';
            $html .= '<td class="right">₹' . number_format($entry, 2) . '</td>';
            $html .= '<td class="right">' . ($exit !== null ? '₹' . number_format($exit, 2) : '—') . '</td>';
            $html .= '<td class="right">' . ($mark !== null ? '₹' . number_format($mark, 2) : '—') . '</td>';
            $html .= '<td class="right">' . $inst->tradingAccount?->brokerage_percent . '%</td>';
            $html .= '<td class="right neg">-₹' . number_format($entryBrok, 2) . '</td>';
            $html .= '<td class="right neg">' . ($exitBrok > 0 ? '-₹' . number_format($exitBrok, 2) : '—') . '</td>';
            $html .= '<td class="right neg">-₹' . number_format($entryBrok + $exitBrok, 2) . '</td>';
            $html .= '<td class="right ' . $fTd . '">' . $fStr . '</td>';
            $html .= '<td class="right ' . $rTd . '">' . $rStr . '</td>';
            $html .= '<td>' . optional($p->open_datetime)->format('d M y H:i') . '</td>';
            $html .= '<td>' . (optional($p->close_datetime)->format('d M y H:i') ?? '—') . '</td>';
            $html .= '<td><span class="badge ' . ($status === 'Closed' ? 'badge-closed' : ($status === 'Mark' ? 'badge-mark' : 'badge-open')) . '">' . $status . '</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '<tfoot><tr>';
        $html .= '<td colspan="11">TOTALS</td>';
        $html .= '<td class="right">-₹' . number_format($totalEntryBrok, 2) . '</td>';
        $html .= '<td class="right">-₹' . number_format($totalExitBrok, 2) . '</td>';
        $html .= '<td class="right">-₹' . number_format($totalBrok, 2) . '</td>';
        $html .= '<td class="right ' . ($totalFloating >= 0 ? 'pos' : 'neg') . '">' . ($totalFloating >= 0 ? '+' : '') . '₹' . number_format($totalFloating, 2) . '</td>';
        $html .= '<td class="right ' . ($totalRealized >= 0 ? 'pos' : 'neg') . '">' . ($totalRealized >= 0 ? '+' : '') . '₹' . number_format($totalRealized, 2) . '</td>';
        $html .= '<td colspan="3"></td>';
        $html .= '</tr></tfoot></table>';

        // Auto-print script
        $html .= '<script>window.onload=function(){window.print();}</script>';
        $html .= '</body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function export(Request $request)
    {
        $accountId = $request->filled('account_id') && $request->account_id !== 'all'
            ? (int) $request->account_id
            : $this->selectedTradingAccountId($request);

        $query = $this->basePositionQuery($request->filled('account_id') && $request->account_id === 'all' ? null : $accountId);
        $this->applyPositionFilters($query, $request);

        $positions = $query->with(['instrument.tradingAccount', 'fills', 'tags'])->orderBy('open_datetime', 'desc')->get();
        
        $headers = [
            'Serial No',        // A
            'Trading Account',  // B
            'Script Name',      // C
            'Segment',          // D
            'Trade Side',       // E
            'Lots / Qty',       // F  (number of lots for F&O, shares for equity)
            'Entry Price',      // G
            'Exit Price',       // H
            'Lot Size',         // I
            'Total Shares',     // J  = F × I
            'Market Price',     // K
            'Brokerage %',      // L
            'Entry Brokerage',  // M
            'Exit Brokerage',   // N
            'Total Brokerage',  // O
            'Floating P&L',     // P
            'Realized P&L',     // Q
            'Opened Date',      // R
            'Closed Date',      // S
            'Status',           // T
        ];
        
        $rows = [];
        $rows[] = $headers;
        foreach ($positions as $index => $position) {
            $instrument = $position->instrument;
            $account = $instrument->tradingAccount;
            $multiplier = (int) ($instrument->multiplier ?? 1);
            $brokerageRate = (float) ($account?->brokerage_percent ?? 0);
            $brokerageFactor = $brokerageRate / 100;
            $quantity = (float) $position->quantity;
            $entryPrice = $position->entryAveragePrice() ?? (float) $position->cost_basis;
            $exitPrice = $position->exitAveragePrice();
            $tradeSide = $position->tradeSide();
            $marketPrice = $position->isOpen() && $position->markPrice() !== null
                ? round($position->markPrice(), 2)
                : '';

            $rNum = $index + 2;

            $entryBrokerageCell = [
                'formula' => 'ROUND(G'.$rNum.'*F'.$rNum.'*I'.$rNum.'*(L'.$rNum.'/100),2)',
                'val'     => round($position->entryBrokerageAmount(), 2),
            ];

            // Exit brokerage: use exit price if closed, mark price if open with mark, else 0
            $exitBrokVal = '';
            if ($position->isClosed()) {
                $exitBrokVal = round($position->exitBrokerageAmount(), 2);
            } elseif ($position->markPrice() !== null) {
                $exitBrokVal = round($position->exitBrokerageAmount($position->markPrice()), 2);
            }
            $exitBrokerageCell = [
                'formula' => 'ROUND(IF(S'.$rNum.'<>"",H'.$rNum.'*F'.$rNum.'*I'.$rNum.'*(L'.$rNum.'/100),IF(K'.$rNum.'<>"",K'.$rNum.'*F'.$rNum.'*I'.$rNum.'*(L'.$rNum.'/100),0)),2)',
                'val'     => $exitBrokVal !== '' ? $exitBrokVal : 0,
            ];

            $totalBrokerageCell = [
                'formula' => 'ROUND(M'.$rNum.'+N'.$rNum.',2)',
                'val'     => round($position->entryBrokerageAmount() + ($exitBrokVal !== '' ? $exitBrokVal : 0), 2),
            ];

            // Floating P&L: only for open positions that have a market price
            $floatingVal = '';
            if ($position->isOpen() && !$position->isMarked() && $position->floatingPnL() !== null) {
                $floatingVal = round($position->floatingPnL(), 2);
            }
            $floatingCell = [
                'formula' => 'ROUND(IF(S'.$rNum.'<>"","",IF(K'.$rNum.'="","",IF(E'.$rNum.'="BUY",(K'.$rNum.'-G'.$rNum.')*F'.$rNum.'*I'.$rNum.'-M'.$rNum.',(G'.$rNum.'-K'.$rNum.')*F'.$rNum.'*I'.$rNum.'-M'.$rNum.'))),2)',
                'val'     => $floatingVal,
            ];

            // Realized P&L: use stored value directly; formula recalculates from prices
            $realizedVal = '';
            if ($position->isClosed() && $position->realized_pnl !== null) {
                $realizedVal = round((float) $position->realized_pnl, 2);
            } elseif ($position->isMarked()) {
                $realizedVal = round((float) $position->markRealizedPnL(), 2);
            }
            $pnlCell = [
                'formula' => 'ROUND(IF(S'.$rNum.'<>"",IF(E'.$rNum.'="BUY",(H'.$rNum.'-G'.$rNum.')*F'.$rNum.'*I'.$rNum.'-M'.$rNum.'-N'.$rNum.',(G'.$rNum.'-H'.$rNum.')*F'.$rNum.'*I'.$rNum.'-M'.$rNum.'-N'.$rNum.'),""),2)',
                'val'     => $realizedVal,
            ];

            $statusCell = [
                'formula' => 'IF(S'.$rNum.'<>"","Closed",IF(K'.$rNum.'<>"","Mark","Open"))',
                'val'     => $position->isClosed() ? 'Closed' : ($position->isMarked() ? 'Mark' : 'Open'),
                'type'    => 'str',
            ];

            $rows[] = [
                $index + 1,                                                          // A
                $account?->name ?? 'Main Trading Account',                           // B
                $instrument->symbol,                                                 // C
                $instrument->asset_type === 'STK' ? 'Equity' : 'F&O',              // D
                $tradeSide,                                                          // E
                $quantity,                                                           // F  lots/shares
                round($entryPrice, 2),                                               // G  entry price
                $exitPrice !== null ? round($exitPrice, 2) : '',                    // H  exit price
                $multiplier,                                                         // I  lot size
                ['formula' => 'F'.$rNum.'*I'.$rNum, 'val' => $quantity * $multiplier], // J  total shares
                $marketPrice,                                                        // K  market price
                $brokerageRate,                                                      // L  brokerage %
                $entryBrokerageCell,                                                 // M
                $exitBrokerageCell,                                                  // N
                $totalBrokerageCell,                                                 // O
                $floatingCell,                                                       // P
                $pnlCell,                                                            // Q
                optional($position->open_datetime)->format('Y-m-d H:i:s'),          // R
                optional($position->close_datetime)->format('Y-m-d H:i:s'),         // S
                $statusCell,                                                         // T
            ];
        }

        if (count($positions) > 0) {
            $firstDataRow = 2;
            $lastDataRow = count($positions) + 1;
            $totalsRowNum = $lastDataRow + 1;

            $rows[] = [
                '',   // A
                'TOTALS', // B
                '', '', '', '', '', '', '', '', '', // C-L
                ['formula' => 'SUM(M'.$firstDataRow.':M'.$lastDataRow.')', 'val' => round((float) $positions->sum(fn (Position $p) => $p->entryBrokerageAmount()), 2)],
                ['formula' => 'SUM(N'.$firstDataRow.':N'.$lastDataRow.')', 'val' => round((float) $positions->sum(fn (Position $p) => $p->isClosed() ? $p->exitBrokerageAmount() : ($p->markPrice() !== null ? $p->exitBrokerageAmount($p->markPrice()) : 0)), 2)],
                ['formula' => 'SUM(O'.$firstDataRow.':O'.$lastDataRow.')', 'val' => round((float) $positions->sum(fn (Position $p) => $p->entryBrokerageAmount() + ($p->isClosed() ? $p->exitBrokerageAmount() : ($p->markPrice() !== null ? $p->exitBrokerageAmount($p->markPrice()) : 0))), 2)],
                ['formula' => 'SUM(P'.$firstDataRow.':P'.$lastDataRow.')', 'val' => round((float) $positions->sum(fn (Position $p) => !$p->isClosed() && !$p->isMarked() ? ($p->floatingPnL() ?? 0) : 0), 2)],
                ['formula' => 'SUM(Q'.$firstDataRow.':Q'.$lastDataRow.')', 'val' => round((float) $positions->sum(fn (Position $p) => $p->isClosed() ? ($p->realized_pnl ?? 0) : ($p->isMarked() ? ($p->markRealizedPnL() ?? 0) : 0)), 2)],
                '', '', '', // R, S, T
            ];
        }
        $filename = 'tradelog-trade-logs-' . now()->format('Y-m-d-His') . '.xlsx';
        
        // Use a secure unique file name in temp folder
        $tempFile = tempnam(sys_get_temp_dir(), 'tradelog_xlsx_');
        if ($tempFile === false) {
            abort(500, 'Unable to create export file.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create XLSX archive.');
        }

        // Row count must be calculated AFTER all rows (including totals) are appended
        $lastColumn  = $this->columnLetter(count($headers));
        $totalRows   = count($rows);   // header + data + optional totals row

        // OOXML strict element order: dimension → sheetViews → cols → sheetData → autoFilter
        $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                   . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $sheetXml .= '<dimension ref="A1:' . $lastColumn . $totalRows . '"/>';

        // Freeze the header row (sheetViews must come before cols)
        $sheetXml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
                   . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
                   . '</sheetView></sheetViews>';

        // Column widths (cols must come before sheetData)
        $sheetXml .= '<cols>';
        $sheetXml .= '<col min="1"  max="1"  width="8"  customWidth="1"/>';  // Serial No
        $sheetXml .= '<col min="2"  max="2"  width="24" customWidth="1"/>';  // Trading Account
        $sheetXml .= '<col min="3"  max="3"  width="18" customWidth="1"/>';  // Script Name
        $sheetXml .= '<col min="4"  max="4"  width="10" customWidth="1"/>';  // Segment
        $sheetXml .= '<col min="5"  max="5"  width="10" customWidth="1"/>';  // Trade Side
        $sheetXml .= '<col min="6"  max="6"  width="10" customWidth="1"/>';  // Lots/Qty
        $sheetXml .= '<col min="7"  max="7"  width="13" customWidth="1"/>';  // Entry Price
        $sheetXml .= '<col min="8"  max="8"  width="13" customWidth="1"/>';  // Exit Price
        $sheetXml .= '<col min="9"  max="9"  width="10" customWidth="1"/>';  // Lot Size
        $sheetXml .= '<col min="10" max="10" width="13" customWidth="1"/>';  // Total Shares
        $sheetXml .= '<col min="11" max="11" width="13" customWidth="1"/>';  // Market Price
        $sheetXml .= '<col min="12" max="12" width="13" customWidth="1"/>';  // Brokerage %
        $sheetXml .= '<col min="13" max="13" width="16" customWidth="1"/>';  // Entry Brokerage
        $sheetXml .= '<col min="14" max="14" width="16" customWidth="1"/>';  // Exit Brokerage
        $sheetXml .= '<col min="15" max="15" width="16" customWidth="1"/>';  // Total Brokerage
        $sheetXml .= '<col min="16" max="16" width="16" customWidth="1"/>';  // Floating P&L
        $sheetXml .= '<col min="17" max="17" width="16" customWidth="1"/>';  // Realized P&L
        $sheetXml .= '<col min="18" max="18" width="22" customWidth="1"/>';  // Opened Date
        $sheetXml .= '<col min="19" max="19" width="22" customWidth="1"/>';  // Closed Date
        $sheetXml .= '<col min="20" max="20" width="10" customWidth="1"/>';  // Status
        $sheetXml .= '</cols>';

        $sheetXml .= '<sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $sheetXml .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($row as $colIndex => $value) {
                $cellRef = $this->columnLetter($colIndex + 1) . ($rowIndex + 1);

                if ($value === null || $value === '') {
                    continue;
                }

                if (is_array($value) && isset($value['formula'])) {
                    // XML-escape the formula so characters like <> don't break the file
                    $formula    = htmlspecialchars($value['formula'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
                    $isStrType  = isset($value['type']) && $value['type'] === 'str';
                    $typeAttr   = $isStrType ? ' t="str"' : '';   // numeric formulas: no t attribute
                    $sheetXml  .= '<c r="' . $cellRef . '"' . $typeAttr . '><f>' . $formula . '</f>';
                    if (isset($value['val']) && $value['val'] !== null && $value['val'] !== '') {
                        // String formula cached value must also be XML-escaped
                        $cachedVal = $isStrType
                            ? htmlspecialchars((string) $value['val'], ENT_XML1 | ENT_COMPAT, 'UTF-8')
                            : $this->formatExcelNumeric($value['val']);
                        $sheetXml .= '<v>' . $cachedVal . '</v>';
                    }
                    $sheetXml .= '</c>';
                } elseif (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && !preg_match('/^0\d/', $value))) {
                    // Numeric cell — NO t attribute (omitting t means numeric in OOXML)
                    $sheetXml .= '<c r="' . $cellRef . '"><v>' . $this->formatExcelNumeric($value) . '</v></c>';
                } else {
                    $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t xml:space="preserve">'
                               . $this->xlsxEscape((string) $value) . '</t></is></c>';
                }
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData>';
        // autoFilter must come AFTER sheetData (strict OOXML element order)
        $sheetXml .= '<autoFilter ref="A1:' . $lastColumn . '1"/>';
        $sheetXml .= '</worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Tradelog</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Trade Logs</vt:lpstr></vt:vector></TitlesOfParts><Company>Tradelog</Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>1.0</AppVersion></Properties>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>Tradelog</dc:creator><cp:lastModifiedBy>Tradelog</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . now()->toAtomString() . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . now()->toAtomString() . '</dcterms:modified></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr/><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Trade Logs" sheetId="1" r:id="rId1"/></sheets><calcPr calcMode="auto" calcId="124519" fullCalcOnLoad="1" forceFullCalc="1"/></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        if (ob_get_level()) {
            ob_end_clean();
        }

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    public function attachTag(Position $position, TradeTag $tag)
    {
        // Ensure user owns this position and tag
        if ($position->instrument->user_id !== auth()->id() || $tag->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Check if tag is already attached
        if ($position->tags()->where('trade_tag_id', $tag->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tag already attached to this trade.'
            ], 422);
        }

        $position->tags()->attach($tag->id);

        return response()->json([
            'success' => true,
            'message' => 'Tag attached successfully.',
            'tag' => $tag,
        ]);
    }

    public function detachTag(Position $position, TradeTag $tag)
    {
        // Ensure user owns this position and tag
        if ($position->instrument->user_id !== auth()->id() || $tag->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $position->tags()->detach($tag->id);

        return response()->json([
            'success' => true,
            'message' => 'Tag removed successfully.'
        ]);
    }

    public function saveBrokerCredentials(Request $request)
    {
        $request->validate([
            'flex_token' => 'required|string',
            'query_id' => 'required|string',
        ]);

        $user = auth()->user();
        $user->ib_flex_token = $request->flex_token;
        $user->ib_query_id = $request->query_id;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Broker credentials saved successfully.'
        ]);
    }

    public function autoImport(Request $request)
    {
        // Get credentials from user if not provided in request
        $user = auth()->user();
        $flexToken = $request->input('flex_token', $user->ib_flex_token);
        $queryId = $request->input('query_id', $user->ib_query_id);
        $accountId = (int) $request->input('account_id', $this->selectedTradingAccountId($request));

        // Validate that we have credentials
        if (empty($flexToken) || empty($queryId)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide both Flex Token and Query ID in settings first.'
            ], 422);
        }

        try {
            Log::info('Auto Import: Starting', [
                'user_id' => $user->id,
                'query_id' => $queryId
            ]);

            // Create or find the "Imported" tag for this user
            $importedTag = TradeTag::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => 'Imported',
                ],
                [
                    'color' => '#6366F1', // Indigo color
                ]
            );

            Log::info('Auto Import: Imported tag ready', [
                'tag_id' => $importedTag->id
            ]);

            // Get all existing positions before import to identify new ones
            $existingPositionIds = Position::whereHas('instrument', function($q) use ($user) {
                $q->where('user_id', $user->id);
                $q->where('trading_account_id', $accountId);
            })->pluck('id')->toArray();

            // Use the Interactive Broker Flex Service
            $flexService = new \App\Services\InteractiveBrokerFlexService();
            
            // Import the report (this handles both request and retrieval with retries)
            $importResult = $flexService->importReport($flexToken, $queryId);
            
            if (!$importResult['success']) {
                Log::error('Auto Import: Failed to retrieve report', [
                    'error' => $importResult['error']
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $importResult['message'] ?? $importResult['error']
                ], 500);
            }

            // Now we have the CSV data, let's process it
            $csvData = $importResult['csvData'];
            
            // Save CSV to a temporary file for processing
            $tempFile = tmpfile();
            $tempFilePath = stream_get_meta_data($tempFile)['uri'];
            fwrite($tempFile, $csvData);
            
            // Create a mock UploadedFile object
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempFilePath,
                'ib_auto_import.csv',
                'text/csv',
                null,
                true
            );
            
            Log::info('Auto Import: Processing CSV data', [
                'csv_size' => strlen($csvData)
            ]);

            // Parse the CSV using existing logic
            $result = $this->parseInteractiveBrokerCSV($uploadedFile, $accountId);
            
            // Close and delete temp file
            fclose($tempFile);

            // Get all newly created positions
            $newPositions = Position::whereHas('instrument', function($q) use ($user) {
                $q->where('user_id', $user->id);
                $q->where('trading_account_id', $accountId);
            })->whereNotIn('id', $existingPositionIds)->get();

            // Tag all new positions with "Imported" tag
            $taggedCount = 0;
            foreach ($newPositions as $position) {
                // Check if position doesn't already have this tag
                if (!$position->tags()->where('trade_tag_id', $importedTag->id)->exists()) {
                    $position->tags()->attach($importedTag->id);
                    $taggedCount++;
                }
            }
            
            Log::info('Auto Import: Completed successfully', array_merge($result, [
                'tagged_positions' => $taggedCount
            ]));

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$result['fills']} fills for {$result['instruments']} instruments. Created {$result['positions']} positions.",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Auto Import: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error during auto import: ' . $e->getMessage()
            ], 500);
        }
    }
}

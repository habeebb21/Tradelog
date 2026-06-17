@extends('layouts.app')

@section('title', 'Trade Details')

@section('content')
<div class="max-w-6xl mx-auto">
    <!-- Header with Back Button -->
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('trades') }}" class="inline-flex items-center text-gray-600 hover:text-gray-900 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Trades
            </a>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openEditModal()" class="px-4 py-2 bg-gray-900 hover:bg-black text-white text-sm font-semibold rounded-lg shadow transition-colors flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Trade
            </button>
            @if(!$position->isClosed())
                {{-- Mark price: Set (mark mode) / Save (floating) / Clear --}}
                <form method="POST" action="{{ route('trades.market-price.update', $position) }}" class="flex items-center gap-1">
                    @csrf
                    @method('PATCH')
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="current_price"
                        value="{{ $position->instrument->current_price !== null ? number_format($position->instrument->current_price, 4, '.', '') : '' }}"
                        placeholder="Mark price"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500 bg-white text-gray-900"
                    >
                    <div class="flex flex-col gap-1">
                        <button type="submit" name="mark_mode" value="1"
                            class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-md transition-colors leading-tight"
                            title="Set — treat as closed at this price">Set</button>
                        <button type="submit" name="mark_mode" value="0"
                            class="px-3 py-1 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold rounded-md transition-colors leading-tight"
                            title="Save — keep open, show floating P&L">Save</button>
                    </div>
                    @if($position->instrument->current_price !== null)
                        <button type="submit" name="current_price" value=""
                            class="px-2 py-2 bg-gray-200 hover:bg-gray-300 text-gray-600 text-sm font-bold rounded-lg transition-colors"
                            title="Clear mark price">×</button>
                    @endif
                </form>
                <button onclick="openCloseModal()" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    Close Position
                </button>
            @endif
        </div>
    </div>

    <!-- Position Summary Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Position Summary</h2>
        </div>
        <div class="px-6 py-5">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Symbol -->
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</label>
                    <div class="mt-2">
                        <div class="text-lg font-semibold text-gray-900">{{ $position->instrument->symbol }}</div>
                        @if($position->instrument->underlying_symbol)
                            <div class="text-sm text-gray-500">{{ $position->instrument->underlying_symbol }}</div>
                        @endif
                        <div class="text-xs text-gray-500 mt-1">
                            Account: {{ $position->instrument->tradingAccount?->name ?? 'Main Trading Account' }}
                        </div>
                    </div>
                </div>

                <!-- Asset Type -->
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Segment</label>
                    <div class="mt-2">
                        @if($position->instrument->isOption())
                            <div class="text-lg font-semibold text-gray-900">
                                {{ $position->instrument->put_call === 'C' ? 'CE' : 'PE' }} Option
                            </div>
                            <div class="text-sm text-gray-500">
                                Strike: {{ inr($position->instrument->strike) }}
                            </div>
                            @if($position->instrument->expiry)
                                <div class="text-sm text-gray-500">
                                    Expiry: {{ $position->instrument->expiry->format('M d, Y') }}
                                </div>
                            @endif
                            <div class="text-sm text-gray-500">
                                Lot Size: {{ $position->instrument->multiplier }}
                            </div>
                        @elseif($position->instrument->asset_type === 'FUT')
                            <div class="text-lg font-semibold text-gray-900">
                                Futures
                            </div>
                            @if($position->instrument->expiry)
                                <div class="text-sm text-gray-500">
                                    Expiry: {{ $position->instrument->expiry->format('M d, Y') }}
                                </div>
                            @endif
                            <div class="text-sm text-gray-500">
                                Lot Size: {{ $position->instrument->multiplier }}
                            </div>
                        @else
                            <div class="text-lg font-semibold text-gray-900">Equity</div>
                        @endif
                        @if(!$position->isClosed())
                            <div class="text-xs text-gray-500 mt-2">
                                Floating P&L uses the instrument's current price.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Quantity -->
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</label>
                    <div class="mt-2 text-lg font-semibold text-gray-900">
                        {{ number_format($position->quantity, 2) }} 
                        @if($position->instrument->isOption())
                            lots
                        @else
                            shares
                        @endif
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Status</label>
                    <div class="mt-2">
                        @if($position->isClosed())
                            @if($position->isProfitable())
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    Win
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    Loss
                                </span>
                            @endif
                        @elseif($position->isMarked())
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                </svg>
                                Mark
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <svg class="w-4 h-4 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                </svg>
                                Open
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Open Details -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 bg-green-50">
                <h3 class="text-md font-semibold text-green-900">Position Opened</h3>
            </div>
            <div class="px-6 py-5">
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Date & Time</label>
                        <div class="mt-1 text-sm text-gray-900">
                            {{ $position->open_datetime->format('F d, Y') }}
                            <span class="text-gray-500">at {{ $position->open_datetime->format('h:i:s A') }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Cost / Contract</label>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ inr($position->cost_basis, 4) }}
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Total Cost Basis</label>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ inr($position->cost_basis * $position->quantity * $position->instrument->multiplier) }}
                        </div>
                    </div>
                    @if($position->instrument->isOption())
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase">Avg Premium</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ inr($position->cost_basis, 4) }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Close Details -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200
                {{ $position->isClosed() ? 'bg-red-50' : ($position->isMarked() ? 'bg-purple-50' : 'bg-gray-50') }}">
                <h3 class="text-md font-semibold
                    {{ $position->isClosed() ? 'text-red-900' : ($position->isMarked() ? 'text-purple-900' : 'text-gray-700') }}">
                    @if($position->isClosed()) Position Closed
                    @elseif($position->isMarked()) Mark Price (as-if closed)
                    @else Position Still Open
                    @endif
                </h3>
            </div>
            <div class="px-6 py-5">
                @php
                    $currentPrice   = $position->markPrice();
                    $floatingPnl    = $position->floatingPnL();
                    $markPnl        = $position->markRealizedPnL();
                    $entryBrokerage = $position->entryBrokerageAmount();
                    // Exit brokerage only applies to closed or mark-mode positions
                    $exitBrokerage  = ($position->isClosed())
                                        ? $position->exitBrokerageAmount()
                                        : ($position->isMarked() ? $position->exitBrokerageAmount($currentPrice) : 0.0);
                    $totalBrokerage = $entryBrokerage + $exitBrokerage;
                @endphp
                @if($position->isClosed())
                    {{-- ── CLOSED ──────────────────────────────────────── --}}
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Realized P&L</label>
                                <div class="mt-2 text-lg {{ pnl_class($position->realized_pnl) }}">
                                    {{ pnl_formatted($position->realized_pnl) }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Entry Brokerage</label>
                                <div class="mt-2 text-lg font-semibold text-rose-600">-{{ inr($entryBrokerage) }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Exit Brokerage</label>
                                <div class="mt-2 text-lg font-semibold text-rose-600">-{{ inr($exitBrokerage) }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Total Brokerage</label>
                                <div class="mt-2 text-lg font-semibold text-rose-600">-{{ inr($totalBrokerage) }}</div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs font-medium text-gray-500 uppercase">Date & Time</label>
                                <div class="mt-1 text-sm text-gray-900">
                                    {{ $position->close_datetime->format('F d, Y') }}
                                    <span class="text-gray-500">at {{ $position->close_datetime->format('h:i:s A') }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500 uppercase">Duration</label>
                                <div class="mt-1 text-sm text-gray-900">
                                    {{ $position->open_datetime->diff($position->close_datetime)->format('%d days, %h hours') }}
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif($position->isMarked())
                    {{-- ── MARK MODE (as-if closed at mark price) ─────── --}}
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="rounded-lg border border-purple-200 bg-purple-50 p-4">
                                <label class="text-xs font-medium text-purple-600 uppercase">Mark P&L <span class="normal-case text-purple-400">@ {{ inr($currentPrice, 4) }}</span></label>
                                <div class="mt-2 text-lg {{ pnl_class($markPnl) }}">
                                    {{ pnl_formatted($markPnl) }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Entry Brokerage</label>
                                <div class="mt-2 text-lg font-semibold text-rose-600">-{{ inr($entryBrokerage) }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Exit Brokerage</label>
                                <div class="mt-2 text-lg font-semibold text-rose-600">-{{ inr($exitBrokerage) }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Total Brokerage</label>
                                <div class="mt-2 text-lg font-semibold text-rose-600">-{{ inr($totalBrokerage) }}</div>
                            </div>
                        </div>
                        <p class="text-xs text-purple-500 italic">Position is still open — values shown as-if closed at the mark price.</p>
                    </div>

                @else
                    {{-- ── OPEN (plain open — exit brokerage does not apply yet) ── --}}
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">
                                    Current Price
                                    @if($currentPrice !== null)
                                        <span class="normal-case text-orange-500 font-normal">(saved)</span>
                                    @endif
                                </label>
                                <div class="mt-2 text-lg font-semibold text-gray-900">
                                    {{ $currentPrice !== null ? inr($currentPrice, 4) : '—' }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Floating P&L</label>
                                <div class="mt-2 text-lg {{ $floatingPnl !== null ? pnl_class($floatingPnl) : 'text-gray-400' }}">
                                    {{ $floatingPnl !== null ? pnl_formatted($floatingPnl) : '—' }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="text-xs font-medium text-gray-500 uppercase">Entry Brokerage</label>
                                <div class="mt-2 text-lg font-semibold text-rose-600">-{{ inr($entryBrokerage) }}</div>
                            </div>
                        </div>
                        @if($currentPrice === null)
                            <div class="text-center py-2">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">Position is still open</p>
                                <p class="mt-1 text-xs text-gray-400">Set a mark price to see P&L estimates.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Fills History -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Execution History</h2>
            <p class="text-sm text-gray-500 mt-1">All fills related to this instrument</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Side</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fees</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exec ID</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $totalFees = 0;
                    @endphp
                    @foreach($position->instrument->fills as $fill)
                        @php
                            $total = ($fill->price * $fill->quantity * $position->instrument->multiplier);
                            if ($fill->side === 'SELL') {
                                $total = -$total;
                            }
                            $totalFees += $fill->fees;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $fill->datetime->format('M d, Y') }}
                                <span class="block text-xs text-gray-500">{{ $fill->datetime->format('H:i:s') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($fill->side === 'BUY')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        BUY
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        SELL
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ number_format($fill->quantity, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ inr($fill->price) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ inr($fill->fees) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $fill->side === 'BUY' ? 'text-red-600' : 'text-green-600' }}">
                                {{ $fill->side === 'BUY' ? '-' : '+' }}{{ inr(abs($total)) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                                {{ $fill->exec_id }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-700">
                            Total Fees:
                        </td>
                        <td colspan="3" class="px-6 py-3 text-sm font-semibold text-gray-900">
                            {{ inr($totalFees) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Tags Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Trade Tags</h2>
            <p class="text-sm text-gray-500 mt-1">Categorize this trade by setup type</p>
        </div>
        <div class="px-6 py-5">
            <!-- Current Tags -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Tags</label>
                <div id="current-tags" class="flex flex-wrap gap-2">
                    @forelse($position->tags as $tag)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-white" style="background-color: {{ $tag->color }}">
                            {{ $tag->name }}
                            <button 
                                type="button" 
                                onclick="removeTag({{ $tag->id }}, '{{ $tag->name }}')"
                                class="ml-2 text-white hover:text-gray-200"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </span>
                    @empty
                        <p class="text-sm text-gray-500">No tags assigned</p>
                    @endforelse
                </div>
            </div>

            <!-- Available Tags -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Add Tags</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($availableTags as $tag)
                        @if(!$position->tags->contains($tag->id))
                            <button 
                                type="button"
                                onclick="addTag({{ $tag->id }}, '{{ $tag->name }}', '{{ $tag->color }}')"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium border-2 transition-colors hover:opacity-80"
                                style="border-color: {{ $tag->color }}; color: {{ $tag->color }}"
                                id="available-tag-{{ $tag->id }}"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                {{ $tag->name }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Trade Notes</h2>
            <p class="text-sm text-gray-500 mt-1">Add notes about this trade for future reference (supports rich text, images, and formatting)</p>
        </div>
        <div class="px-6 py-5">
            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <form id="notesForm" action="{{ route('trades.update', $position) }}" method="POST">
                @csrf
                @method('PATCH')
                
                <div class="mb-4">
                    <!-- Toolbar -->
                    <div class="border border-gray-300 rounded-t-lg bg-gray-50 p-2 flex flex-wrap gap-1">
                        <!-- Text Formatting -->
                        <button type="button" onclick="formatText('bold')" class="p-2 hover:bg-gray-200 rounded transition" title="Bold">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M11 5H7v10h4c2.21 0 4-1.79 4-4s-1.79-4-4-4zm-2 8v-2h2c1.1 0 2 .9 2 2s-.9 2-2 2H9zm0-4V7h2c1.1 0 2 .9 2 2s-.9 2-2 2H9z"/>
                            </svg>
                        </button>
                        
                        <button type="button" onclick="formatText('italic')" class="p-2 hover:bg-gray-200 rounded transition" title="Italic">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 4H8l-2 12h2l2-12zm2 0h2l-2 12h-2l2-12z"/>
                            </svg>
                        </button>
                        
                        <button type="button" onclick="formatText('underline')" class="p-2 hover:bg-gray-200 rounded transition" title="Underline">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 16c-2.76 0-5-2.24-5-5V4h2v7c0 1.66 1.34 3 3 3s3-1.34 3-3V4h2v7c0 2.76-2.24 5-5 5zm-6 2h12v2H4v-2z"/>
                            </svg>
                        </button>
                        
                        <div class="w-px bg-gray-300 mx-1"></div>
                        
                        <!-- Headings -->
                        <button type="button" onclick="formatText('formatBlock', 'h1')" class="px-3 py-2 hover:bg-gray-200 rounded transition font-bold" title="Heading 1">
                            H1
                        </button>
                        
                        <button type="button" onclick="formatText('formatBlock', 'h2')" class="px-3 py-2 hover:bg-gray-200 rounded transition font-semibold" title="Heading 2">
                            H2
                        </button>
                        
                        <button type="button" onclick="formatText('formatBlock', 'h3')" class="px-3 py-2 hover:bg-gray-200 rounded transition font-medium" title="Heading 3">
                            H3
                        </button>
                        
                        <div class="w-px bg-gray-300 mx-1"></div>
                        
                        <!-- Lists -->
                        <button type="button" onclick="formatText('insertUnorderedList')" class="p-2 hover:bg-gray-200 rounded transition" title="Bullet List">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 7h2V5H3v2zm0 4h2V9H3v2zm0 4h2v-2H3v2zm4-8v2h10V7H7zm0 4h10V9H7v2zm0 4h10v-2H7v2z"/>
                            </svg>
                        </button>
                        
                        <button type="button" onclick="formatText('insertOrderedList')" class="p-2 hover:bg-gray-200 rounded transition" title="Numbered List">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5 15H3v-2h2v2zm0-4H3V9h2v2zm0-4H3V5h2v2zm4 8h10v-2H9v2zm0-4h10V9H9v2zm0-4h10V5H9v2z"/>
                            </svg>
                        </button>
                        
                        <div class="w-px bg-gray-300 mx-1"></div>
                        
                        <!-- Alignment -->
                        <button type="button" onclick="formatText('justifyLeft')" class="p-2 hover:bg-gray-200 rounded transition" title="Align Left">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 5h14v2H3V5zm0 4h10v2H3V9zm0 4h14v2H3v-2zm0 4h10v2H3v-2z"/>
                            </svg>
                        </button>
                        
                        <button type="button" onclick="formatText('justifyCenter')" class="p-2 hover:bg-gray-200 rounded transition" title="Align Center">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 5h14v2H3V5zm2 4h10v2H5V9zm-2 4h14v2H3v-2zm2 4h10v2H5v-2z"/>
                            </svg>
                        </button>
                        
                        <button type="button" onclick="formatText('justifyRight')" class="p-2 hover:bg-gray-200 rounded transition" title="Align Right">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 5h14v2H3V5zm4 4h10v2H7V9zm-4 4h14v2H3v-2zm4 4h10v2H7v-2z"/>
                            </svg>
                        </button>
                        
                        <div class="w-px bg-gray-300 mx-1"></div>
                        
                        <!-- Clear Formatting -->
                        <button type="button" onclick="formatText('removeFormat')" class="p-2 hover:bg-gray-200 rounded transition" title="Clear Formatting">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M18.59 7L12 13.59 5.41 7 4 8.41l8 8 8-8L18.59 7z"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Editable Content Area -->
                    <div 
                        id="notesEditor" 
                        contenteditable="true" 
                        class="border border-gray-300 border-t-0 rounded-b-lg p-6 min-h-[300px] max-h-[500px] overflow-y-auto focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white"
                        placeholder="Add notes about this trade... (strategy, emotions, lessons learned, etc.)"
                    >{!! old('notes', $position->notes) !!}</div>
                    
                    <input type="hidden" name="notes" id="notesInput">
                    
                    @error('notes')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button 
                        type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modals for Edit and Close -->
    @php
        $tradeSide = $position->tradeSide();
        $exitSide = $tradeSide === 'BUY' ? 'SELL' : 'BUY';
        $entryFill = $position->instrument->fills->where('side', $tradeSide)->sortBy('datetime')->first();
        $exitFill = $position->instrument->fills->where('side', $exitSide)->sortByDesc('datetime')->first();
        $brokeragePercent = $position->brokerageRate();
    @endphp

    <!-- Edit Trade Modal -->
    <div id="edit-trade-modal" class="hidden fixed inset-0 bg-gray-950 bg-opacity-70 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden border border-gray-200 transform transition-all text-gray-900">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-900">Edit Trade Details</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form action="{{ route('trades.update', $position) }}" method="POST" class="p-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="edit_trade" value="1">
                <input type="hidden" id="edit_brokerage_percent" value="{{ number_format($brokeragePercent, 4, '.', '') }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Symbol -->
                    <div>
                        <label for="edit_symbol" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Script Name</label>
                        <input type="text" name="symbol" id="edit_symbol" value="{{ $position->instrument->symbol }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>
<div>
    <label for="edit_trade_side" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Trade Side</label>
    <select name="trade_side" id="edit_trade_side" required onchange="updateEditTradeSideFields()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
        <option value="BUY" {{ $tradeSide === 'BUY' ? 'selected' : '' }}>BUY</option>
        <option value="SELL" {{ $tradeSide === 'SELL' ? 'selected' : '' }}>SELL</option>
    </select>
</div>
                    <!-- Quantity -->
                    <div>
                        <label for="edit_quantity" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Quantity</label>
                        <input type="number" step="0.01" name="quantity" id="edit_quantity" value="{{ $position->quantity }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                    <!-- Entry Price -->
                    <div>
                        <label for="edit_entry_price" class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            <span id="edit_entry_price_label">Buy Price (₹) *</span>
                        </label>
                        <input type="number" step="0.01" name="entry_price" id="edit_entry_price" value="{{ $entryFill ? number_format($entryFill->price, 4, '.', '') : number_format($position->cost_basis, 4, '.', '') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                    <!-- Current Price -->
                    <div>
                        <label for="edit_current_price" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Current Price (â‚¹)</label>
                        <input type="number" step="0.01" name="current_price" id="edit_current_price" value="{{ $position->instrument->current_price ? number_format($position->instrument->current_price, 4, '.', '') : '' }}" placeholder="Mark price for floating P&L" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                    <!-- Opened Date -->
                    <div>
                        <label for="edit_opened_date" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Opened Date</label>
                        <input type="datetime-local" name="opened_date" id="edit_opened_date" value="{{ $position->open_datetime->format('Y-m-d\TH:i') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                    @if($position->instrument->isOption())
                        <!-- Option Type -->
                        <div>
                            <label for="edit_option_type" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Option Type</label>
                            <select name="option_type" id="edit_option_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                                <option value="CE" {{ $position->instrument->put_call === 'C' ? 'selected' : '' }}>CE (Call Option)</option>
                                <option value="PE" {{ $position->instrument->put_call === 'P' ? 'selected' : '' }}>PE (Put Option)</option>
                            </select>
                        </div>
                        <!-- Strike Price -->
                        <div>
                            <label for="edit_strike_price" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Strike Price (â‚¹)</label>
                            <input type="number" step="0.01" name="strike_price" id="edit_strike_price" value="{{ $position->instrument->strike ? number_format($position->instrument->strike, 2, '.', '') : '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                        </div>
                        <!-- Expiration Date -->
                        <div>
                            <label for="edit_expiration_date" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Expiration Date</label>
                            <input type="date" name="expiration_date" id="edit_expiration_date" value="{{ $position->instrument->expiry ? $position->instrument->expiry->format('Y-m-d') : '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                        </div>
                        <!-- Lot Size -->
                        <div>
                            <label for="edit_multiplier" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Lot Size</label>
                            <input type="number" name="multiplier" id="edit_multiplier" value="{{ $position->instrument->multiplier }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                        </div>
                    @endif

                    <!-- Exit Details -->
                    <div class="col-span-1 md:col-span-2 border-t border-gray-100 pt-4 mt-2">
                        <h4 class="text-sm font-bold text-gray-800 mb-3">Exit Details (Optional - Leave blank if Open)</h4>
                    </div>

                    <!-- Exit Price -->
                    <div>
                        <label for="edit_exit_price" class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            <span id="edit_exit_price_label">Sell Price (₹)</span>
                        </label>
                        <input type="number" step="0.01" name="exit_price" id="edit_exit_price" value="{{ $exitFill ? number_format($exitFill->price, 4, '.', '') : '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                    <!-- Closed Date -->
                    <div>
                        <label for="edit_closed_date" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Closed Date</label>
                        <input type="datetime-local" name="closed_date" id="edit_closed_date" value="{{ $position->close_datetime ? $position->close_datetime->format('Y-m-d\TH:i') : '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-100">
                   <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-sm transition-colors">
                       Cancel
                   </button>
                   <button type="submit" class="px-4 py-2 bg-gray-900 hover:bg-black text-white font-semibold rounded-lg text-sm transition-colors shadow-md">
                       Save Changes
                   </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Close Position Modal -->
    <div id="close-position-modal" class="hidden fixed inset-0 bg-gray-950 bg-opacity-70 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 overflow-hidden border border-gray-200 transform transition-all text-gray-900">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-900">Close Position</h3>
                <button onclick="closeCloseModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form action="{{ route('trades.update', $position) }}" method="POST" class="p-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="close_trade" value="1">

                <div class="space-y-4">
                    <!-- Exit Price -->
                    <div>
                        <label for="close_exit_price" class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            <span id="close_exit_price_label">{{ $tradeSide === 'BUY' ? 'Sell Price (₹) *' : 'Buy Price (₹) *' }}</span>
                        </label>
                        <input type="number" step="0.01" name="exit_price" id="close_exit_price" required placeholder="e.g. 15.50" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                    <!-- Closed Date -->
                    <div>
                        <label for="close_closed_date" class="block text-xs font-semibold text-gray-700 uppercase mb-1">Closed Date</label>
                        <input type="datetime-local" name="closed_date" id="close_closed_date" required value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 bg-white text-gray-900">
                    </div>

                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeCloseModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-sm transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg text-sm transition-colors shadow-md">
                        Close Position
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateEditTradeSideFields() {
        const tradeSide = document.getElementById('edit_trade_side').value;
        const entryPriceInput = document.getElementById('edit_entry_price');
        const exitPriceInput = document.getElementById('edit_exit_price');
        const closedDateInput = document.getElementById('edit_closed_date');

        if (tradeSide === 'SELL') {
            document.getElementById('edit_entry_price_label').textContent = 'Sell Price (₹) *';
            document.getElementById('edit_exit_price_label').textContent = 'Buy Price (₹)';
            document.getElementById('edit_entry_fees_label').textContent = 'Entry Sell Fees (₹)';
            document.getElementById('edit_exit_fees_label').textContent = 'Exit Buy Fees (₹)';
        } else {
            document.getElementById('edit_entry_price_label').textContent = 'Buy Price (₹) *';
            document.getElementById('edit_exit_price_label').textContent = 'Sell Price (₹)';
            document.getElementById('edit_entry_fees_label').textContent = 'Entry Buy Fees (₹)';
            document.getElementById('edit_exit_fees_label').textContent = 'Exit Sell Fees (₹)';
        }

        entryPriceInput.required = true;
        exitPriceInput.required = false;

        if (exitPriceInput.value && !closedDateInput.value) {
            closedDateInput.setCustomValidity('Closed Date is required when an exit price is provided.');
        } else {
            closedDateInput.setCustomValidity('');
        }
    }

    function openEditModal() {
        document.getElementById('edit-trade-modal').classList.remove('hidden');
        updateEditTradeSideFields();
    }
    function closeEditModal() {
        document.getElementById('edit-trade-modal').classList.add('hidden');
    }
    function openCloseModal() {
        document.getElementById('close-position-modal').classList.remove('hidden');
    }
    function closeCloseModal() {
        document.getElementById('close-position-modal').classList.add('hidden');
    }
    async function addTag(tagId, tagName, tagColor) {
        try {
            const response = await fetch(`/trades/{{ $position->id }}/tags/${tagId}/attach`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            if (data.success) {
                // Remove from available tags
                const availableBtn = document.getElementById(`available-tag-${tagId}`);
                if (availableBtn) {
                    availableBtn.remove();
                }

                // Add to current tags
                const currentTags = document.getElementById('current-tags');
                
                // Remove "No tags assigned" message if exists
                const noTagsMsg = currentTags.querySelector('.text-gray-500');
                if (noTagsMsg) {
                    noTagsMsg.remove();
                }

                const tagHtml = `
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-white" style="background-color: ${tagColor}" id="current-tag-${tagId}">
                        ${tagName}
                        <button type="button" onclick="removeTag(${tagId}, '${tagName}')" class="ml-2 text-white hover:text-gray-200">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </span>
                `;
                currentTags.insertAdjacentHTML('beforeend', tagHtml);
                
                showMessage('success', data.message);
            } else {
                showMessage('error', data.message);
            }
        } catch (error) {
            showMessage('error', 'An error occurred while adding the tag');
            console.error('Error:', error);
        }
    }

    async function removeTag(tagId, tagName) {
        try {
            const response = await fetch(`/trades/{{ $position->id }}/tags/${tagId}/detach`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            if (data.success) {
                // Remove from current tags
                const currentTag = document.getElementById(`current-tag-${tagId}`);
                if (currentTag) {
                    currentTag.remove();
                }

                // Check if no tags left
                const currentTags = document.getElementById('current-tags');
                if (currentTags.children.length === 0) {
                    currentTags.innerHTML = '<p class="text-sm text-gray-500">No tags assigned</p>';
                }

                // Add back to available tags
                location.reload(); // Reload to refresh available tags list
                
                showMessage('success', data.message);
            } else {
                showMessage('error', data.message);
            }
        } catch (error) {
            showMessage('error', 'An error occurred while removing the tag');
            console.error('Error:', error);
        }
    }

    function showMessage(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `mb-4 rounded-md p-4 ${type === 'success' ? 'bg-green-50' : 'bg-red-50'}`;
        alertDiv.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 ${type === 'success' ? 'text-green-400' : 'text-red-400'}" viewBox="0 0 20 20" fill="currentColor">
                        ${type === 'success' 
                            ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />'
                            : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />'
                        }
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium ${type === 'success' ? 'text-green-800' : 'text-red-800'}">${message}</p>
                </div>
            </div>
        `;

        const container = document.querySelector('.px-6.py-5');
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }

    // Rich Text Editor Functions
    function formatText(command, value = null) {
        document.execCommand(command, false, value);
        document.getElementById('notesEditor').focus();
    }

    // Handle form submission for notes
    document.getElementById('notesForm').addEventListener('submit', function(e) {
        const editorContent = document.getElementById('notesEditor').innerHTML;
        document.getElementById('notesInput').value = editorContent;
    });
    document.addEventListener('DOMContentLoaded', function() {
        updateEditTradeSideFields();

        const editExitPrice = document.getElementById('edit_exit_price');
        const editClosedDate = document.getElementById('edit_closed_date');
        if (editExitPrice && editClosedDate) {
            editExitPrice.addEventListener('input', updateEditTradeSideFields);
            editClosedDate.addEventListener('input', updateEditTradeSideFields);
        }
    });
</script>

<style>
    /* Rich Text Editor Placeholder */
    #notesEditor:empty:before {
        content: attr(placeholder);
        color: #9CA3AF;
        cursor: text;
    }

    /* Rich Text Editor Content Styling */
    #notesEditor h1 {
        font-size: 2em;
        font-weight: bold;
        margin: 0.67em 0;
    }

    #notesEditor h2 {
        font-size: 1.5em;
        font-weight: bold;
        margin: 0.75em 0;
    }

    #notesEditor h3 {
        font-size: 1.17em;
        font-weight: bold;
        margin: 0.83em 0;
    }

    #notesEditor ul, #notesEditor ol {
        margin: 1em 0;
        padding-left: 2em;
    }

    #notesEditor ul {
        list-style-type: disc;
    }

    #notesEditor ol {
        list-style-type: decimal;
    }

    #notesEditor p {
        margin: 0.5em 0;
    }

    #notesEditor img {
        max-width: 100%;
        height: auto;
        margin: 1em 0;
    }

    #notesEditor strong {
        font-weight: bold;
    }

    #notesEditor em {
        font-style: italic;
    }

    #notesEditor u {
        text-decoration: underline;
    }
</style>
@endsection


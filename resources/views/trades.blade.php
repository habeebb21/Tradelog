@extends('layouts.app')

@section('title', 'Trades')

@section('content')
<div class="max-w-full overflow-hidden">
<!-- Confirmation Modal -->
<div id="confirmation-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0">
                    <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900" id="modal-title">Confirm Deletion</h3>
                </div>
            </div>
            <p class="text-gray-600 mb-6" id="modal-message">Are you sure you want to delete this position? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition-colors">
                    Cancel
                </button>
                <button onclick="confirmDelete()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 md:mb-8">
    <div>
        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Trades</h2>
        <p class="text-sm md:text-base text-gray-600">View and manage your Indian market trade book</p>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
        <a href="{{ route('trades.export', request()->except('page')) }}" class="w-full sm:w-auto bg-gray-900 hover:bg-black text-white font-semibold px-4 md:px-6 py-3 rounded-lg shadow-md transition-colors duration-200 flex items-center justify-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-8m0 0l-3 3m3-3l3 3m-9 4h12"></path>
            </svg>
            Export Excel
        </a>
        <a href="{{ route('trades.export-pdf', request()->except('page')) }}" target="_blank" class="w-full sm:w-auto bg-rose-600 hover:bg-rose-700 text-white font-semibold px-4 md:px-6 py-3 rounded-lg shadow-md transition-colors duration-200 flex items-center justify-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
            Export PDF
        </a>
        <a href="{{ route('trades.create') }}" class="w-full sm:w-auto bg-orange-600 hover:bg-orange-700 text-white font-semibold px-4 md:px-6 py-3 rounded-lg shadow-md transition-colors duration-200 flex items-center justify-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Trade
        </a>
    </div>
</div>

@if(isset($tradeSummary))
<div class="trading-panel mb-6">
    <div class="trading-panel-header">
        <div>
            <div class="text-lg font-bold text-gray-900 dark:text-white">
                {{ $accountSummary ? ($accounts->firstWhere('id', $accountId)?->name ?? 'Trade Summary') : 'Trade Summary' }}
            </div>
            <div class="text-sm text-gray-500 dark:text-slate-400">
                {{ $accountSummary ? 'Live metrics for the selected account' : 'Totals for the trades currently shown' }}
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm font-bold px-3 py-1 rounded-full {{ $tradeSummary['grand_total'] >= 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' }}">
                {{ $tradeSummary['grand_total'] >= 0 ? '+' : '' }}{{ inr($tradeSummary['grand_total']) }} {{ $tradeSummary['grand_total'] >= 0 ? 'Cr.' : 'Dr.' }}
            </span>
            <span class="trading-badge trading-badge-live">LIVE</span>
        </div>
    </div>
    <div class="trading-metric-grid">
        @if($accountSummary)
            <div class="trading-metric-cell">
                <div class="trading-metric-label">Balance</div>
                <div class="trading-metric-value">{{ inr($accountSummary['balance']) }}</div>
            </div>
            <div class="trading-metric-cell">
                <div class="trading-metric-label">Equity</div>
                <div class="trading-metric-value text-cyan-600 dark:text-cyan-400">{{ inr($accountSummary['equity']) }}</div>
            </div>
        @endif
        <div class="trading-metric-cell">
            <div class="trading-metric-label">Floating P&amp;L</div>
            <div class="trading-metric-value {{ pnl_class($tradeSummary['total_floating_pnl']) }}">{{ pnl_formatted($tradeSummary['total_floating_pnl']) }}</div>
        </div>
        <div class="trading-metric-cell">
            <div class="trading-metric-label">Realized P&amp;L</div>
            <div class="trading-metric-value {{ pnl_class($tradeSummary['total_realized_pnl']) }}">{{ pnl_formatted($tradeSummary['total_realized_pnl']) }}</div>
        </div>
        <div class="trading-metric-cell">
            <div class="trading-metric-label">Total Dr / Cr</div>
            <div class="trading-metric-value {{ pnl_class($tradeSummary['grand_total']) }}">
                {{ pnl_formatted($tradeSummary['grand_total']) }}
                <span class="text-sm font-semibold ml-1">{{ $tradeSummary['grand_total'] >= 0 ? 'Cr.' : 'Dr.' }}</span>
            </div>
        </div>
        <div class="trading-metric-cell">
            <div class="trading-metric-label">Total Brokerage</div>
            <div class="trading-metric-value text-violet-600 dark:text-violet-400">{{ inr($tradeSummary['total_brokerage']) }}</div>
        </div>
    </div>
    <div class="flex flex-wrap gap-3 px-5 py-4 border-t border-gray-200 dark:border-slate-800 text-sm text-gray-600 dark:text-slate-300 bg-gray-50 dark:bg-slate-900/50">
        <span class="rounded-full bg-white dark:bg-white/5 border border-gray-200 dark:border-slate-700 px-3 py-1">Open: {{ $tradeSummary['open_positions_count'] }}</span>
        <span class="rounded-full bg-white dark:bg-white/5 border border-gray-200 dark:border-slate-700 px-3 py-1">Closed: {{ $tradeSummary['closed_positions_count'] }}</span>
    </div>
</div>
@endif

<!-- Success/Error Messages -->
@if(session('success'))
    <div class="flash-message mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif

@if(session('error'))
    <div class="flash-message mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>{{ session('error') }}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif

<!-- Trades Table Card -->
<div class="trading-card w-full">
    <!-- Filters Section -->
    <div class="px-4 md:px-6 py-4 bg-gray-50 border-b border-gray-200 overflow-x-hidden">
        <!-- Filter Toggle Button -->
        <button 
            type="button" 
            onclick="toggleFilters()" 
            class="w-full flex items-center justify-between mb-3 px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg transition-colors duration-200"
        >
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <span class="font-semibold text-gray-700">Filters</span>
                @if(request()->except(['page', 'per_page']))
                    <span class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-700 text-xs font-medium rounded-full">
                        Active
                    </span>
                @endif
            </div>
            <svg id="filter-chevron" class="w-5 h-5 text-gray-600 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <form method="GET" action="{{ route('trades') }}" id="filter-form" class="hidden">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3 md:gap-4">
                <!-- Symbol Search -->
                <div>
                    <label for="symbol" class="block text-xs font-medium text-gray-700 mb-1">Symbol</label>
                    <input 
                        type="text" 
                        name="symbol" 
                        id="symbol" 
                        value="{{ request('symbol') }}" 
                        placeholder="e.g. SPX, ORCL"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <!-- Account Filter -->
                <div>
                    <label for="account_id" class="block text-xs font-medium text-gray-700 mb-1">Trading Account</label>
                    <select 
                        name="account_id" 
                        id="account_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="all" {{ request('account_id') === 'all' ? 'selected' : '' }}>All Accounts</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ (string) request('account_id', $accountId) === (string) $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Trade State -->
                <div>
                    <label for="state" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select 
                        name="state" 
                        id="state"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="">All Positions</option>
                        <option value="open" {{ request('state') == 'open' ? 'selected' : '' }}>Open</option>
                        <option value="closed" {{ request('state') == 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>

                <!-- Asset Type -->
                <div>
                    <label for="asset_type" class="block text-xs font-medium text-gray-700 mb-1">Segment</label>
                    <select 
                        name="asset_type" 
                        id="asset_type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="">All Segments</option>
                        <option value="OPT" {{ request('asset_type') == 'OPT' ? 'selected' : '' }}>F&O Options</option>
                        <option value="STK" {{ request('asset_type') == 'STK' ? 'selected' : '' }}>Equity</option>
                    </select>
                </div>

                <!-- Option Type (CALL/PUT) -->
                <div>
                    <label for="put_call" class="block text-xs font-medium text-gray-700 mb-1">Option Side</label>
                    <select 
                        name="put_call" 
                        id="put_call"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="">All Options</option>
                        <option value="CALL" {{ request('put_call') == 'CALL' ? 'selected' : '' }}>CALL</option>
                        <option value="PUT" {{ request('put_call') == 'PUT' ? 'selected' : '' }}>PUT</option>
                    </select>
                </div>

                <!-- P&L Filter -->
                <div>
                    <label for="pnl_filter" class="block text-xs font-medium text-gray-700 mb-1">PnL</label>
                    <select 
                        name="pnl_filter" 
                        id="pnl_filter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="">All Trades</option>
                        <option value="winner" {{ request('pnl_filter') == 'winner' ? 'selected' : '' }}>Winners</option>
                        <option value="loser" {{ request('pnl_filter') == 'loser' ? 'selected' : '' }}>Losers</option>
                        <option value="breakeven" {{ request('pnl_filter') == 'breakeven' ? 'selected' : '' }}>Break-even</option>
                    </select>
                </div>

                <!-- Tag Filter -->
                <div>
                    <label for="tag" class="block text-xs font-medium text-gray-700 mb-1">Tag</label>
                    <select 
                        name="tag" 
                        id="tag"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="">All Tags</option>
                        @foreach($userTags as $tag)
                            <option value="{{ $tag->id }}" {{ request('tag') == $tag->id ? 'selected' : '' }}>
                                {{ $tag->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Opened Date From -->
                <div>
                    <label for="opened_from" class="block text-xs font-medium text-gray-700 mb-1">Opened From</label>
                    <input 
                        type="date" 
                        name="opened_from" 
                        id="opened_from" 
                        value="{{ request('opened_from') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <!-- Opened Date To -->
                <div>
                    <label for="opened_to" class="block text-xs font-medium text-gray-700 mb-1">Opened To</label>
                    <input 
                        type="date" 
                        name="opened_to" 
                        id="opened_to" 
                        value="{{ request('opened_to') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <!-- Closed Date From -->
                <div>
                    <label for="closed_from" class="block text-xs font-medium text-gray-700 mb-1">Closed From</label>
                    <input 
                        type="date" 
                        name="closed_from" 
                        id="closed_from" 
                        value="{{ request('closed_from') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <!-- Closed Date To -->
                <div>
                    <label for="closed_to" class="block text-xs font-medium text-gray-700 mb-1">Closed To</label>
                    <input 
                        type="date" 
                        name="closed_to" 
                        id="closed_to" 
                        value="{{ request('closed_to') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <!-- Sort By -->
                <div>
                    <label for="sort_by" class="block text-xs font-medium text-gray-700 mb-1">Sort By</label>
                    <select 
                        name="sort_by" 
                        id="sort_by"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="close_datetime" {{ request('sort_by', 'close_datetime') == 'close_datetime' ? 'selected' : '' }}>Closed Date</option>
                        <option value="open_datetime" {{ request('sort_by') == 'open_datetime' ? 'selected' : '' }}>Opened Date</option>
                        <option value="symbol" {{ request('sort_by') == 'symbol' ? 'selected' : '' }}>Symbol</option>
                        <option value="pnl" {{ request('sort_by') == 'pnl' ? 'selected' : '' }}>P&L</option>
                    </select>
                </div>

                <!-- Order -->
                <div>
                    <label for="sort_order" class="block text-xs font-medium text-gray-700 mb-1">Order</label>
                    <select 
                        name="sort_order" 
                        id="sort_order"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                        <option value="desc" {{ request('sort_order', 'desc') == 'desc' ? 'selected' : '' }}>Descending</option>
                        <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>Ascending</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-end gap-2 col-span-1 sm:col-span-2">
                    <button 
                        type="submit"
                        class="flex-1 sm:flex-none px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-md transition-colors whitespace-nowrap"
                    >
                        Apply Filters
                    </button>
                    <a 
                        href="{{ route('trades.export', array_merge(request()->except('page'), ['account_id' => request('account_id', $accountId ?? 'all')])) }}"
                        class="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2 bg-gray-900 hover:bg-black text-white text-sm font-medium rounded-md transition-colors whitespace-nowrap"
                    >
                        Download
                    </a>
                    <a 
                        href="{{ route('trades.export-pdf', array_merge(request()->except('page'), ['account_id' => request('account_id', $accountId ?? 'all')])) }}"
                        target="_blank"
                        class="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-md transition-colors whitespace-nowrap"
                    >
                        PDF
                    </a>
                    <a 
                        href="{{ route('trades') }}"
                        class="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-md transition-colors whitespace-nowrap"
                    >
                        Clear
                    </a>
                </div>
            </div>

            <!-- Preserve per_page in filters -->
            <input type="hidden" name="per_page" value="{{ request('per_page', 30) }}">
        </form>
    </div>

    <div class="px-4 md:px-6 py-4 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-4">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-semibold text-gray-900">Trade History</h3>
                <button id="bulk-delete-btn" onclick="deleteSelected()" class="hidden px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs sm:text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                    Delete Selected
                </button>
            </div>
            
            <!-- Per Page Selector -->
            <div class="flex items-center space-x-2">
                <label for="per-page" class="text-xs sm:text-sm text-gray-600 whitespace-nowrap">Show:</label>
                <select id="per-page" onchange="changePerPage(this.value)" class="px-2 sm:px-3 py-1.5 border border-gray-300 rounded-lg text-xs sm:text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="30" {{ request('per_page', 30) == 30 ? 'selected' : '' }}>30</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>All</option>
                </select>
                <span class="text-xs sm:text-sm text-gray-600 hidden sm:inline whitespace-nowrap">entries</span>
            </div>
        </div>
    </div>
    
    <!-- Horizontally Scrollable Table Container -->
    <div class="trading-table-wrap w-full">
        <table class="trading-table">
            <thead>
                <tr>
                    <th class="w-10">
                        <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    </th>
                    <th>Opened</th>
                    <th>Closed</th>
                    <th>Script</th>
                    <th>Account</th>
                    <th>Segment</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Entry</th>
                    <th class="text-right">Exit</th>
                    <th class="text-right">Market</th>
                    <th class="text-right">Entry Br.</th>
                    <th class="text-right">Exit Br.</th>
                    <th class="text-right">Floating P&amp;L</th>
                    <th class="text-right">Realized P&amp;L</th>
                    <th>Tags</th>
                    <th>Status</th>
                    <th class="text-right w-8"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $position)
                <tr class="group cursor-pointer position-row" onclick="window.location='{{ route('trades.show', $position) }}'">
                    <td onclick="event.stopPropagation()">
                        <input type="checkbox" name="position_ids[]" value="{{ $position->id }}" onchange="updateBulkDeleteButton()" class="position-checkbox w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    </td>
                    <td>
                        <div class="font-medium text-gray-900 dark:text-white">{{ $position->open_datetime->format('M d, Y') }}</div>
                        <div class="text-xs text-gray-500 dark:text-slate-400">{{ $position->open_datetime->format('H:i:s') }}</div>
                    </td>
                    <td>
                        @if($position->isClosed())
                            <div class="font-medium text-gray-900 dark:text-white">{{ $position->close_datetime->format('M d, Y') }}</div>
                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ $position->close_datetime->format('H:i:s') }}</div>
                        @else
                            <span class="text-gray-400 dark:text-slate-500">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $position->instrument->symbol }}</div>
                        @if($position->instrument->underlying_symbol)
                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ $position->instrument->underlying_symbol }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="trading-badge bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-200">
                            {{ $position->instrument->tradingAccount?->name ?? 'Main Trading Account' }}
                        </span>
                    </td>
                    <td>
                        <span class="trading-badge {{ $position->instrument->asset_type === 'STK' ? 'trading-badge-segment-equity' : 'trading-badge-segment-fo' }}">
                            {{ $position->instrument->asset_type === 'STK' ? 'Equity' : 'F&O' }}
                        </span>
                        @if($position->instrument->isOption())
                            <span class="ml-1 trading-badge {{ $position->instrument->put_call === 'C' ? 'trading-badge-win' : 'trading-badge-loss' }}">
                                {{ $position->instrument->put_call === 'C' ? 'CE' : 'PE' }}
                            </span>
                        @endif
                    </td>
                    <td class="text-right">
                        @php
                            $mult = (int) ($position->instrument->multiplier ?? 1);
                            $qty  = (float) $position->quantity;
                        @endphp
                        @if($position->instrument->asset_type !== 'STK' && $mult > 1)
                            {{-- F&O: total shares on top (colored), lot breakdown below (small grey) --}}
                            <span class="text-base font-bold text-cyan-600 dark:text-cyan-400">
                                {{ number_format($qty * $mult, 0) }}
                            </span>
                            <div class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">
                                {{ number_format($qty, 0) }} lot{{ $qty != 1 ? 's' : '' }} × {{ number_format($mult, 0) }}
                            </div>
                        @else
                            <span class="text-base font-bold text-cyan-600 dark:text-cyan-400">
                                {{ number_format($qty, 0) }}
                            </span>
                            <div class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">shares</div>
                        @endif
                    </td>
                    <td class="text-right">
                        {{ inr($position->cost_basis) }}
                    </td>
                    <td class="text-right">
                        @if($position->isClosed())
                            {{ inr($position->exitAveragePrice() ?? 0) }}
                        @else
                            <span class="text-gray-400 dark:text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="text-right" onclick="event.stopPropagation()">
                        @if($position->isClosed())
                            <div class="text-sm font-medium text-gray-900">
                                {{ $position->instrument->current_price !== null ? inr($position->instrument->current_price) : '-' }}
                            </div>
                            <span class="block text-xs text-gray-500">Saved mark price</span>
                        @else
                            <form method="POST" action="{{ route('trades.market-price.update', $position) }}" class="flex items-center gap-1" onsubmit="event.stopPropagation()" onclick="event.stopPropagation()">
                                @csrf
                                @method('PATCH')
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="current_price"
                                    value="{{ $position->instrument->current_price !== null ? number_format($position->instrument->current_price, 4, '.', '') : '' }}"
                                    placeholder="Mark"
                                    class="w-20 px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-orange-500 focus:border-orange-500"
                                    onclick="event.stopPropagation()"
                                >
                                <div class="flex flex-col gap-1">
                                    {{-- Set: saves price AND enables mark_mode (Mark badge, realized-style P&L) --}}
                                    <button type="submit" name="mark_mode" value="1" onclick="event.stopPropagation()"
                                        class="px-2 py-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-md transition-colors leading-tight"
                                        title="Set mark price — shows realized-style P&L">
                                        Set
                                    </button>
                                    {{-- Save: saves price only, keeps floating P&L / Open status --}}
                                    <button type="submit" name="mark_mode" value="0" onclick="event.stopPropagation()"
                                        class="px-2 py-1 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-md transition-colors leading-tight"
                                        title="Save price — shows floating P&L against this price">
                                        Save
                                    </button>
                                </div>
                                @if($position->instrument->current_price !== null)
                                    <button type="submit" name="current_price" value="" onclick="event.stopPropagation()"
                                        class="px-2 py-1.5 bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 text-xs font-medium rounded-md transition-colors"
                                        title="Clear mark price">
                                        ×
                                    </button>
                                @endif
                            </form>
                        @endif
                    </td>
                    <td class="text-right text-violet-600 dark:text-violet-400">{{ inr($position->entryBrokerageAmount()) }}</td>
                    <td class="text-right text-violet-600 dark:text-violet-400">
                        @if($position->isClosed())
                            {{ inr($position->exitBrokerageAmount()) }}
                        @elseif($position->isMarked())
                            {{ inr($position->exitBrokerageAmount($position->markPrice())) }}
                        @else
                            <span class="text-gray-400 dark:text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="text-right col-pnl">
                        @if($position->isClosed() || $position->isMarked())
                            <span class="pnl-neutral">—</span>
                        @else
                            @php $floatingPnl = $position->floatingPnL(); @endphp
                            @if($floatingPnl !== null)
                                <span class="{{ pnl_class($floatingPnl) }}">{{ pnl_formatted($floatingPnl) }}</span>
                            @else
                                <span class="pnl-neutral">—</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-right col-pnl">
                        @if($position->isClosed())
                            <span class="{{ pnl_class($position->realized_pnl) }}">{{ pnl_formatted($position->realized_pnl) }}</span>
                        @elseif($position->isMarked())
                            @php $mPnl = $position->markRealizedPnL(); @endphp
                            <span class="{{ pnl_class($mPnl) }}">{{ pnl_formatted($mPnl) }}</span>
                            <span class="block text-xs text-purple-500 dark:text-purple-400">@ mark</span>
                        @else
                            <span class="pnl-neutral">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach($position->tags as $tag)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-white" style="background-color: {{ $tag->color }}">
                                    {{ $tag->name }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        @if($position->isClosed())
                            @if($position->isProfitable())
                                <span class="trading-badge trading-badge-win">Win</span>
                            @else
                                <span class="trading-badge trading-badge-loss">Loss</span>
                            @endif
                        @elseif($position->isMarked())
                            <span class="trading-badge" style="background:rgba(139,92,246,0.15);color:#7c3aed;border:1px solid rgba(139,92,246,0.3);">Mark</span>
                        @else
                            <span class="trading-badge trading-badge-open">Open</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-orange-500 transition-colors inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="16" class="py-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg font-medium">No positions found</p>
                            <p class="text-gray-400 text-sm mt-2">Click "Add Trade" to import your first trades</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($positions->count() > 0)
            @php
                $totalFloatingPnl = 0;
                $totalRealizedPnl = 0;
                $totalEntryBrokerage = 0;
                $totalExitBrokerage = 0;
                $totalBrokerageFooter = 0;
                foreach ($positions as $pos) {
                    $totalEntryBrokerage += $pos->entryBrokerageAmount();
                    if ($pos->isClosed()) {
                        $totalExitBrokerage  += $pos->exitBrokerageAmount();
                        $totalBrokerageFooter += $pos->totalBrokerageAmount();
                        $totalRealizedPnl    += (float) $pos->realized_pnl;
                    } elseif ($pos->isMarked()) {
                        $totalExitBrokerage  += $pos->exitBrokerageAmount($pos->markPrice());
                        $totalBrokerageFooter += $pos->totalBrokerageAmount($pos->markPrice());
                        $mpnl = $pos->markRealizedPnL();
                        if ($mpnl !== null) { $totalFloatingPnl += $mpnl; }
                    } else {
                        // plain-open: no exit brokerage yet
                        $totalBrokerageFooter += $pos->entryBrokerageAmount();
                        $fpnl = $pos->floatingPnL();
                        if ($fpnl !== null) { $totalFloatingPnl += $fpnl; }
                    }
                }
                // grand total = floating + realized (brokerage already baked into both values)
                $grandTotal = $totalFloatingPnl + $totalRealizedPnl;
            @endphp
            <tfoot>
                <tr>
                    <td colspan="10" class="text-right text-gray-700 dark:text-slate-300">Page Totals</td>
                    <td class="text-right text-violet-600 dark:text-violet-400">{{ inr($totalEntryBrokerage) }}</td>
                    <td class="text-right text-violet-600 dark:text-violet-400">{{ inr($totalExitBrokerage) }}</td>
                    <td class="text-right col-pnl {{ pnl_class($totalFloatingPnl) }}">{{ pnl_formatted($totalFloatingPnl) }}</td>
                    <td class="text-right col-pnl {{ pnl_class($totalRealizedPnl) }}">{{ pnl_formatted($totalRealizedPnl) }}</td>
                    <td colspan="3"></td>
                </tr>
                <tr>
                    <td colspan="10" class="text-right text-gray-700 dark:text-slate-300">Total Brokerage</td>
                    <td colspan="2" class="text-right text-violet-600 dark:text-violet-400">{{ inr($totalBrokerageFooter) }}</td>
                    <td colspan="5"></td>
                </tr>
                <tr>
                    <td colspan="11" class="text-right">Grand Total (Dr / Cr)</td>
                    <td colspan="2" class="text-right {{ pnl_class($grandTotal) }}">
                        {{ pnl_formatted($grandTotal) }}
                        <span class="text-xs font-semibold ml-1">{{ $grandTotal >= 0 ? 'Cr.' : 'Dr.' }}</span>
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    
    <!-- Custom Pagination Footer -->
    <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
        <div class="text-sm text-gray-600">
            Showing {{ $positions->firstItem() ?? 0 }} to {{ $positions->lastItem() ?? 0 }} of {{ $positions->total() }} entries
        </div>
        
        <div class="flex items-center space-x-4">
            @if($positions->hasPages() && request('per_page') !== 'all')
                <div class="text-sm text-gray-600">
                    Page {{ $positions->currentPage() }} of {{ $positions->lastPage() }}
                </div>
                
                <div class="flex space-x-1">
                    {{-- Previous Button --}}
                    @if($positions->onFirstPage())
                        <span class="px-3 py-1.5 bg-gray-100 text-gray-400 rounded-lg text-sm cursor-not-allowed">
                            Previous
                        </span>
                    @else
                        <a href="{{ $positions->previousPageUrl() }}&per_page={{ request('per_page', 30) }}" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                            Previous
                        </a>
                    @endif
                    
                    {{-- Page Numbers --}}
                    @php
                        $currentPage = $positions->currentPage();
                        $lastPage = $positions->lastPage();
                        $start = max(1, $currentPage - 2);
                        $end = min($lastPage, $currentPage + 2);
                    @endphp
                    
                    @if($start > 1)
                        <a href="{{ $positions->url(1) }}&per_page={{ request('per_page', 30) }}" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                            1
                        </a>
                        @if($start > 2)
                            <span class="px-3 py-1.5 text-gray-400">...</span>
                        @endif
                    @endif
                    
                    @for($page = $start; $page <= $end; $page++)
                        @if($page == $currentPage)
                            <span class="px-3 py-1.5 bg-orange-600 text-white rounded-lg text-sm font-medium">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $positions->url($page) }}&per_page={{ request('per_page', 30) }}" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                                {{ $page }}
                            </a>
                        @endif
                    @endfor
                    
                    @if($end < $lastPage)
                        @if($end < $lastPage - 1)
                            <span class="px-3 py-1.5 text-gray-400">...</span>
                        @endif
                        <a href="{{ $positions->url($lastPage) }}&per_page={{ request('per_page', 30) }}" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                            {{ $lastPage }}
                        </a>
                    @endif
                    
                    {{-- Next Button --}}
                    @if($positions->hasMorePages())
                        <a href="{{ $positions->nextPageUrl() }}&per_page={{ request('per_page', 30) }}" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                            Next
                        </a>
                    @else
                        <span class="px-3 py-1.5 bg-gray-100 text-gray-400 rounded-lg text-sm cursor-not-allowed">
                            Next
                        </span>
                    @endif
                </div>
            @elseif(request('per_page') === 'all')
                <div class="text-sm text-gray-600">
                    Showing all entries
                </div>
            @endif
        </div>
    </div>
</div>

<script>
// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 500);
        }, 5000);
    });

    // Check if filters are active and show them automatically
    const hasActiveFilters = {{ request()->except(['page', 'per_page']) ? 'true' : 'false' }};
    if (hasActiveFilters) {
        const filterForm = document.getElementById('filter-form');
        if (filterForm) {
            filterForm.classList.remove('hidden');
            const chevron = document.getElementById('filter-chevron');
            if (chevron) {
                chevron.style.transform = 'rotate(180deg)';
            }
        }
    }
});

function toggleFilters() {
    const filterForm = document.getElementById('filter-form');
    const chevron = document.getElementById('filter-chevron');
    
    if (filterForm.classList.contains('hidden')) {
        filterForm.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        filterForm.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}

function changePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page'); // Reset to page 1 when changing per_page
    window.location.href = url.toString();
}

// Preserve filters when changing per_page
document.getElementById('per-page').addEventListener('change', function() {
    const form = document.getElementById('filter-form');
    const perPageInput = form.querySelector('input[name="per_page"]');
    perPageInput.value = this.value;
    form.submit();
});

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.position-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkDeleteButton();
}

function updateBulkDeleteButton() {
    const checkboxes = document.querySelectorAll('.position-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const selectAllCheckbox = document.getElementById('select-all');
    
    if (checkboxes.length > 0) {
        bulkDeleteBtn.classList.remove('hidden');
        bulkDeleteBtn.textContent = `Delete Selected (${checkboxes.length})`;
    } else {
        bulkDeleteBtn.classList.add('hidden');
        selectAllCheckbox.checked = false;
    }
}

let deleteCallback = null;

function showConfirmModal(title, message, callback) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-message').textContent = message;
    document.getElementById('confirmation-modal').classList.remove('hidden');
    deleteCallback = callback;
}

function closeConfirmModal() {
    document.getElementById('confirmation-modal').classList.add('hidden');
    deleteCallback = null;
}

function confirmDelete() {
    if (deleteCallback) {
        deleteCallback();
    }
    closeConfirmModal();
}

function deleteSelected() {
    const checkboxes = document.querySelectorAll('.position-checkbox:checked');
    if (checkboxes.length === 0) {
        return;
    }
    
    const count = checkboxes.length;
    showConfirmModal(
        'Confirm Bulk Deletion',
        `Are you sure you want to delete ${count} position${count > 1 ? 's' : ''}? This action cannot be undone.`,
        function() {
            const positionIds = Array.from(checkboxes).map(cb => cb.value);
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("trades.bulk-delete") }}';
            
            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            // Add position IDs
            positionIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'position_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function deletePosition(positionId) {
    showConfirmModal(
        'Confirm Deletion',
        'Are you sure you want to delete this position? This action cannot be undone.',
        function() {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/trades/${positionId}`;
            
            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            // Add DELETE method
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            form.appendChild(methodInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    );
}
</script>
</div>
@endsection



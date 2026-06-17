@extends('layouts.app')

@section('title', 'Trading Accounts')

@section('content')
<div id="alert-banner" class="hidden mb-6 px-4 py-3 rounded-lg flex items-center justify-between transition-all duration-300">
    <div class="flex items-center">
        <svg id="alert-icon-success" class="w-5 h-5 mr-3 text-green-600 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <svg id="alert-icon-error" class="w-5 h-5 mr-3 text-red-600 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span id="alert-message"></span>
    </div>
    <button onclick="hideAlert()" class="text-gray-500 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
</div>

<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Trading Accounts</h2>
        <p class="text-gray-600 dark:text-slate-400 mt-1">Switch between different broker accounts or strategies without multiple logins</p>
    </div>
    <button
        onclick="openAddModal()"
        class="w-full sm:w-auto bg-orange-600 hover:bg-orange-700 text-white font-semibold px-6 py-3 rounded-lg shadow-md transition-all duration-200 flex items-center justify-center"
    >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Account
    </button>
</div>


<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    @foreach($accounts as $account)
        <div class="trading-account-card {{ $account->id === $activeAccountId ? 'is-active' : '' }}">
            @if($account->id === $activeAccountId)
                <div class="h-1 bg-gradient-to-r from-orange-500 to-amber-400"></div>
            @endif

            <div class="p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $account->name }}</h3>
                            @if($account->id === $activeAccountId)
                                <span class="trading-badge trading-badge-live">Active</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                            {{ $account->broker_name ?? 'Custom Broker' }} · {{ $account->market }}
                        </p>
                        <p class="mt-2 text-xs text-gray-400 dark:text-slate-500">
                            Brokerage: {{ rtrim(rtrim(number_format((float) ($account->brokerage_percent ?? 0), 4, '.', ''), '0'), '.') }}%
                            @if(!is_null($account->starting_balance))
                                · Base Balance: {{ inr($account->starting_balance) }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        @php $acctGrandTotal = round((float)($account->floating_pnl ?? 0) + (float)($account->profit_loss ?? 0) - (float)($account->total_brokerage ?? 0), 2); @endphp
                        <span class="text-sm font-bold px-3 py-1 rounded-full {{ $acctGrandTotal >= 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' }}">
                            {{ $acctGrandTotal >= 0 ? '+' : '' }}{{ inr($acctGrandTotal) }} {{ $acctGrandTotal >= 0 ? 'Cr.' : 'Dr.' }}
                        </span>
                        @if($account->id !== $activeAccountId)
                            <form action="{{ route('accounts.activate', $account) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-xs font-semibold text-gray-700 dark:text-slate-200 transition-colors hover:bg-gray-50 dark:hover:bg-slate-800">
                                    Set Active
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="trading-metric-grid rounded-xl overflow-hidden border border-gray-200 dark:border-slate-800">
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Balance</div>
                        <div class="trading-metric-value {{ pnl_class($account->balance, 'text-gray-900 dark:text-white') }}">{{ inr($account->balance) }}</div>
                    </div>
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Equity</div>
                        <div class="trading-metric-value text-cyan-600 dark:text-cyan-400">{{ inr($account->equity) }}</div>
                    </div>
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Floating P&amp;L</div>
                        <div class="trading-metric-value {{ pnl_class($account->floating_pnl) }}">{{ pnl_formatted($account->floating_pnl) }}</div>
                    </div>
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Realized P&amp;L</div>
                        <div class="trading-metric-value {{ pnl_class($account->profit_loss) }}">{{ pnl_formatted($account->profit_loss) }}</div>
                    </div>
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Total Brokerage</div>
                        <div class="trading-metric-value text-violet-600 dark:text-violet-400">{{ inr($account->total_brokerage) }}</div>
                    </div>
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Closed Trades</div>
                        <div class="trading-metric-value">{{ $account->total_trades }}</div>
                    </div>
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Win Rate</div>
                        <div class="trading-metric-value">{{ $account->win_rate }}%</div>
                    </div>
                    <div class="trading-metric-cell">
                        <div class="trading-metric-label">Open Positions</div>
                        <div class="trading-metric-value">{{ $account->open_positions_count }}</div>
                    </div>
                </div>

                @if($account->notes)
                    <p class="mt-5 text-sm text-gray-600 dark:text-slate-300 italic line-clamp-2" title="{{ $account->notes }}">
                        "{{ $account->notes }}"
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-black/20 px-6 py-4">
                <div class="text-xs text-gray-500 dark:text-slate-400">
                    @if($account->has_initial_balance)
                        Initial balance set
                    @else
                        Set initial balance in Settings
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        data-account-id="{{ $account->id }}"
                        data-account-name="{{ e($account->name) }}"
                        data-account-broker="{{ e($account->broker_name ?? '') }}"
                        data-account-market="{{ e($account->market) }}"
                        data-account-currency="{{ e($account->currency) }}"
                        data-account-balance="{{ $account->starting_balance !== null ? number_format($account->starting_balance, 2, '.', '') : '' }}"
                        data-account-brokerage="{{ $account->brokerage_percent !== null ? number_format($account->brokerage_percent, 4, '.', '') : '' }}"
                        data-account-default="{{ $account->is_default ? '1' : '0' }}"
                        data-account-notes="{{ e($account->notes ?? '') }}"
                        onclick="document.getElementById('edit-id').value=this.dataset.accountId;document.getElementById('edit-name').value=this.dataset.accountName;document.getElementById('edit-broker').value=this.dataset.accountBroker;document.getElementById('edit-market').value=this.dataset.accountMarket;document.getElementById('edit-currency').value=this.dataset.accountCurrency;document.getElementById('edit-starting-balance').value=this.dataset.accountBalance;document.getElementById('edit-brokerage-percent').value=this.dataset.accountBrokerage;document.getElementById('edit-notes').value=this.dataset.accountNotes;document.getElementById('edit-default').checked=this.dataset.accountDefault==='1';document.getElementById('edit-account-form').action='/accounts/' + this.dataset.accountId;document.getElementById('edit-modal').classList.remove('hidden')"
                        class="text-sm font-semibold text-cyan-600 dark:text-cyan-400 hover:text-cyan-500 dark:hover:text-cyan-300 transition-colors"
                    >
                        Edit
                    </button>
                    <button
                        onclick="deleteAccount({{ $account->id }})"
                        class="text-sm font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-500 dark:hover:text-rose-300 transition-colors"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Add Modal -->
<div id="add-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-lg w-full mx-4 border border-gray-200">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Create Trading Account</h3>
        <form id="add-account-form" onsubmit="submitAddAccount(event)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="add-name" class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                    <input type="text" id="add-name" required placeholder="e.g. Zerodha Main, Groww F&O" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="add-broker" class="block text-sm font-medium text-gray-700 mb-1">Broker / Platform</label>
                    <input type="text" id="add-broker" placeholder="e.g. Zerodha, AngelOne, Groww" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="add-starting-balance" class="block text-sm font-medium text-gray-700 mb-1">Account Balance</label>
                    <input type="number" id="add-starting-balance" step="0.01" min="0" placeholder="250000" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="add-brokerage-percent" class="block text-sm font-medium text-gray-700 mb-1">Brokerage %</label>
                    <input type="number" id="add-brokerage-percent" step="0.0001" min="0" max="100" placeholder="0.10" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="add-market" class="block text-sm font-medium text-gray-700 mb-1">Market Segment</label>
                    <input type="text" id="add-market" value="NSE / F&O" placeholder="e.g. NSE Stocks, F&O" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <input type="hidden" id="add-currency" value="INR">
                <div class="md:col-span-2">
                    <label for="add-notes" class="block text-sm font-medium text-gray-700 mb-1">Description / Strategy Notes</label>
                    <textarea id="add-notes" rows="3" placeholder="Optional notes about this account's strategy..." class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 resize-none"></textarea>
                </div>
            </div>
            <label class="inline-flex items-center mt-4 cursor-pointer">
                <input type="checkbox" id="add-default" class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                <span class="ml-2 text-sm text-gray-700 font-medium">Make this my default trading account</span>
            </label>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeAddModal()" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-semibold transition-colors">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-lg w-full mx-4 border border-gray-200">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Edit Trading Account</h3>
        <form id="edit-account-form" method="POST" action="" onsubmit="updateAccount(event)">
            @csrf
            @method('PATCH')
            <input type="hidden" id="edit-id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="edit-name" class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                    <input type="text" id="edit-name" required placeholder="Account name" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="edit-broker" class="block text-sm font-medium text-gray-700 mb-1">Broker / Platform</label>
                    <input type="text" id="edit-broker" placeholder="Broker" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="edit-starting-balance" class="block text-sm font-medium text-gray-700 mb-1">Account Balance</label>
                    <input type="number" id="edit-starting-balance" step="0.01" min="0" placeholder="Account balance" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="edit-brokerage-percent" class="block text-sm font-medium text-gray-700 mb-1">Brokerage %</label>
                    <input type="number" id="edit-brokerage-percent" step="0.0001" min="0" max="100" placeholder="0.10" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="edit-market" class="block text-sm font-medium text-gray-700 mb-1">Market Segment</label>
                    <input type="text" id="edit-market" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <input type="hidden" id="edit-currency" value="INR">
                <div class="md:col-span-2">
                    <label for="edit-notes" class="block text-sm font-medium text-gray-700 mb-1">Description / Strategy Notes</label>
                    <textarea id="edit-notes" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 resize-none"></textarea>
                </div>
            </div>
            <label class="inline-flex items-center mt-4 cursor-pointer">
                <input type="checkbox" id="edit-default" class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                <span class="ml-2 text-sm text-gray-700 font-medium">Make this my default trading account</span>
            </label>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="document.getElementById('edit-modal').classList.add('hidden')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-semibold transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showAlert(type, message) {
        const banner = document.getElementById('alert-banner');
        const msgEl = document.getElementById('alert-message');
        const iconSuccess = document.getElementById('alert-icon-success');
        const iconError = document.getElementById('alert-icon-error');

        msgEl.textContent = message;

        banner.classList.remove('hidden', 'bg-green-50', 'border-green-200', 'text-green-800', 'bg-red-50', 'border-red-200', 'text-red-800');
        iconSuccess.classList.add('hidden');
        iconError.classList.add('hidden');

        if (type === 'success') {
            banner.classList.add('bg-green-50', 'border', 'border-green-200', 'text-green-800');
            iconSuccess.classList.remove('hidden');
        } else {
            banner.classList.add('bg-red-50', 'border', 'border-red-200', 'text-red-800');
            iconError.classList.remove('hidden');
        }

        banner.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideAlert() {
        document.getElementById('alert-banner').classList.add('hidden');
    }

    function openAddModal() {
        document.getElementById('add-modal').classList.remove('hidden');
    }

    function closeAddModal() {
        document.getElementById('add-modal').classList.add('hidden');
        document.getElementById('add-account-form').reset();
    }

    async function submitAddAccount(event) {
        event.preventDefault();
        const name = document.getElementById('add-name').value.trim();
        const broker_name = document.getElementById('add-broker').value.trim();
        const starting_balance = document.getElementById('add-starting-balance').value;
        const brokerage_percent = document.getElementById('add-brokerage-percent').value;
        const market = document.getElementById('add-market').value.trim();
        const currency = document.getElementById('add-currency').value;
        const notes = document.getElementById('add-notes').value.trim();
        const is_default = document.getElementById('add-default').checked;

        try {
            const response = await fetch('{{ route("accounts.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name, broker_name, starting_balance, brokerage_percent, market, currency, notes, is_default })
            });

            const data = await response.json();
            if (data.success) {
                closeAddModal();
                showAlert('success', data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert('error', data.message);
            }
        } catch (e) {
            showAlert('error', 'Failed to create trading account.');
            console.error(e);
        }
    }

    async function updateAccount(event) {
        event.preventDefault();

        const id = document.getElementById('edit-id').value;
        const name = document.getElementById('edit-name').value.trim();
        const broker_name = document.getElementById('edit-broker').value.trim();
        const starting_balance = document.getElementById('edit-starting-balance').value;
        const brokerage_percent = document.getElementById('edit-brokerage-percent').value;
        const market = document.getElementById('edit-market').value.trim();
        const currency = document.getElementById('edit-currency').value;
        const notes = document.getElementById('edit-notes').value.trim();
        const is_default = document.getElementById('edit-default').checked;

        if (!name) {
            showAlert('error', 'Please enter an account name');
            return;
        }

        try {
            const response = await fetch(`/accounts/${id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name, broker_name, starting_balance, brokerage_percent, market, currency, notes, is_default })
            });

            const data = await response.json();
            if (data.success) {
                document.getElementById('edit-modal').classList.add('hidden');
                showAlert('success', data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert('error', data.message || 'Failed to update trading account.');
            }
        } catch (e) {
            showAlert('error', 'Failed to update trading account.');
            console.error(e);
        }
    }

    async function deleteAccount(id) {
        if (!confirm('Are you sure you want to delete this trading account? This will permanently sever links to trades (trades will need to be reassigned). This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/accounts/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert('error', data.message);
            }
        } catch (e) {
            showAlert('error', 'Failed to delete trading account.');
            console.error(e);
        }
    }
</script>
@endsection



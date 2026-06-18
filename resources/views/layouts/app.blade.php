<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(config('app.debug') || ! app()->environment('production'))
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">
    @endif
    <title>{{ config('app.name', 'Tradelog') }} - @yield('title')</title>
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    
    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="bg-gray-900 text-white fixed h-screen overflow-y-auto flex flex-col transition-all duration-300 z-40 md:w-64" style="width: 16rem;">
            <!-- Sidebar Header -->
            <div class="px-6 py-8 border-b border-gray-800">
                <h1 id="sidebar-title" class="text-2xl font-bold text-white">Tradelog</h1>
                <p id="sidebar-user" class="text-xs text-gray-400 mt-1">{{ auth()->user()->name }}</p>
                @php
                    $activeAccountName = auth()->user()->activeTradingAccount()?->name;
                @endphp
                @if($activeAccountName)
                    <div id="sidebar-account-indicator" class="flex items-center mt-2 text-xs text-gray-300">
                        <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                        <span class="truncate" title="{{ $activeAccountName }}">{{ $activeAccountName }}</span>
                    </div>
                @endif
                <button id="theme-toggle" type="button" onclick="toggleTheme()" class="mt-4 inline-flex items-center px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-sm text-gray-100 transition-colors">
                    <svg id="theme-icon-sun" class="w-4 h-4 mr-2 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m0 14v2m8.485-14.485l-1.414 1.414M5.929 18.071l-1.414 1.414m14.142 0l-1.414-1.414M5.929 5.929L4.515 4.515M21 12h-2M5 12H3m9 6a6 6 0 100-12 6 6 0 000 12z"></path>
                    </svg>
                    <svg id="theme-icon-moon" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path>
                    </svg>
                    <span id="theme-toggle-label">Dark Theme</span>
                </button>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="px-3 py-6 flex-1">
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('accounts.index') }}" 
                           class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 {{ request()->routeIs('accounts.index') ? 'bg-orange-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}"
                           title="Accounts">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span class="sidebar-text ml-3">Accounts</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('dashboard') }}" 
                           class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 {{ request()->routeIs('dashboard') ? 'bg-orange-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}"
                           title="Dashboard">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            <span class="sidebar-text ml-3">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('trades') }}" 
                           class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 {{ request()->routeIs('trades') ? 'bg-orange-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}"
                           title="Trades">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="sidebar-text ml-3">Trades</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('diary') }}" 
                           class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 {{ request()->routeIs('diary') ? 'bg-orange-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}"
                           title="Diary">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="sidebar-text ml-3">Diary</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('settings') }}" 
                           class="sidebar-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 {{ request()->routeIs('settings') ? 'bg-orange-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}"
                           title="Settings">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="sidebar-text ml-3">Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Logout Button -->
            <div class="px-3 py-4 border-t border-gray-800">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="sidebar-link flex items-center w-full px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-all duration-200" title="Logout">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="sidebar-text ml-3">Logout</span>
                    </button>
                </form>

                <!-- Exit (stop server) -->
                <button
                    type="button"
                    onclick="confirmExit()"
                    class="sidebar-link flex items-center w-full px-4 py-3 mt-1 rounded-lg text-rose-400 hover:bg-rose-900/30 hover:text-rose-300 transition-all duration-200"
                    title="Exit Tradelog"
                >
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/>
                    </svg>
                    <span class="sidebar-text ml-3">Exit</span>
                </button>
            </div>

            <!-- Exit Confirmation Modal -->
            <div id="exit-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-70 z-50 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl w-full max-w-sm border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Exit Tradelog?</h3>
                            <p class="text-sm text-gray-500 dark:text-slate-400">This will stop the server and close the app.</p>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="document.getElementById('exit-modal').classList.add('hidden')"
                            class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 rounded-lg font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                            Cancel
                        </button>
                        <button type="button" onclick="doExit()"
                            class="flex-1 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-semibold transition-colors">
                            Exit
                        </button>
                    </div>
                </div>
            </div>

            <script>
                function confirmExit() {
                    document.getElementById('exit-modal').classList.remove('hidden');
                }
                async function doExit() {
                    document.getElementById('exit-modal').innerHTML =
                        '<div class="flex flex-col items-center justify-center py-8 gap-3">' +
                        '<svg class="w-10 h-10 text-rose-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
                        '<p class="text-gray-700 dark:text-slate-300 font-medium">Shutting down...</p></div>';
                    try {
                        await fetch('{{ route("app.exit") }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                        });
                    } catch(e) { /* server stopped, expected */ }
                    setTimeout(() => window.close(), 1500);
                }
            </script>
        </aside>
        
        <!-- Toggle Button -->
        <button 
            id="sidebar-toggle" 
            class="fixed top-2 z-50 text-white p-2 rounded-r-md shadow-md transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-orange-500"
            style="left: 16rem; background-color: #1f2937;"
            onmouseover="this.style.backgroundColor='#374151'" 
            onmouseout="this.style.backgroundColor='#1f2937'"
            onclick="toggleSidebar()"
            aria-label="Toggle Sidebar"
        >
            <svg id="toggle-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
            </svg>
        </button>
        
        <!-- Main Content -->
        <main id="main-content" class="flex-1 p-4 md:p-6 lg:p-8 transition-all duration-300 ml-20 md:ml-64 overflow-x-hidden" style="margin-left: 16rem;">
            <div class="w-full max-w-full overflow-x-hidden">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        function syncThemeButton() {
            const isDark = document.documentElement.classList.contains('dark');
            const moon = document.getElementById('theme-icon-moon');
            const sun = document.getElementById('theme-icon-sun');
            const label = document.getElementById('theme-toggle-label');

            if (!moon || !sun || !label) return;

            moon.classList.toggle('hidden', isDark);
            sun.classList.toggle('hidden', !isDark);
            label.textContent = isDark ? 'Light Theme' : 'Dark Theme';
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            syncThemeButton();
        }

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleBtn = document.getElementById('sidebar-toggle');
            const toggleIcon = document.getElementById('toggle-icon');
            const sidebarTexts = document.querySelectorAll('.sidebar-text');
            const sidebarTitle = document.getElementById('sidebar-title');
            const sidebarUser = document.getElementById('sidebar-user');
            
            // Check if sidebar is currently expanded (width is 16rem or 256px)
            const currentWidth = sidebar.offsetWidth;
            const isExpanded = currentWidth > 100; // If width > 100px, it's expanded
            
            if (isExpanded) {
                // Collapse sidebar
                sidebar.style.width = '5rem';
                mainContent.style.marginLeft = '5rem';
                toggleBtn.style.left = '5rem';
                
                // Hide text elements
                sidebarTexts.forEach(text => text.classList.add('hidden'));
                sidebarTitle.classList.add('hidden');
                sidebarUser.classList.add('hidden');
                const accIndicator = document.getElementById('sidebar-account-indicator');
                if (accIndicator) accIndicator.classList.add('hidden');
                
                // Change icon to expand
                toggleIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>';
                
                // Store state
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                // Expand sidebar
                sidebar.style.width = '16rem';
                mainContent.style.marginLeft = '16rem';
                toggleBtn.style.left = '16rem';
                
                // Show text elements
                sidebarTexts.forEach(text => text.classList.remove('hidden'));
                sidebarTitle.classList.remove('hidden');
                sidebarUser.classList.remove('hidden');
                const accIndicator = document.getElementById('sidebar-account-indicator');
                if (accIndicator) accIndicator.classList.remove('hidden');
                
                // Change icon to collapse
                toggleIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>';
                
                // Store state
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
        
        // Restore sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            syncThemeButton();

            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('main-content');
                const toggleBtn = document.getElementById('sidebar-toggle');
                const toggleIcon = document.getElementById('toggle-icon');
                const sidebarTexts = document.querySelectorAll('.sidebar-text');
                const sidebarTitle = document.getElementById('sidebar-title');
                const sidebarUser = document.getElementById('sidebar-user');
                
                // Set collapsed state
                sidebar.style.width = '5rem';
                mainContent.style.marginLeft = '5rem';
                toggleBtn.style.left = '5rem';
                
                // Hide text elements
                sidebarTexts.forEach(text => text.classList.add('hidden'));
                sidebarTitle.classList.add('hidden');
                sidebarUser.classList.add('hidden');
                const accIndicator = document.getElementById('sidebar-account-indicator');
                if (accIndicator) accIndicator.classList.add('hidden');
                
                // Change icon to expand
                toggleIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>';
            }
        });
    </script>
</body>
</html>

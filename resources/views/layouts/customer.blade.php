@php
    use Illuminate\Support\Str;

    $customer = auth('customer')->user();
    $hour = (int) date('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

    $rawLogoPath = config('app.system_logo_path', 'img/logo.png');
    $logoUrl = Str::startsWith($rawLogoPath, ['http://', 'https://', '//'])
        ? $rawLogoPath
        : asset(ltrim($rawLogoPath, '/'));

    $rawFaviconPath = config('app.favicon_path', 'img/favicon_io/favicon.ico');
    $faviconUrl = Str::startsWith($rawFaviconPath, ['http://', 'https://', '//'])
        ? $rawFaviconPath
        : asset(ltrim($rawFaviconPath, '/'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ session('theme', 'light') === 'dark' ? 'dark' : '' }}">
    <head>
	        <meta charset="utf-8">
	        <meta name="viewport" content="width=device-width, initial-scale=1">
	        <meta name="theme-color" content="#151B54">
	        <meta name="csrf-token" content="{{ csrf_token() }}">
	        <title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>
        
        {{-- Favicons --}}
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js/minified/introjs.min.css">
        <style>
            [x-cloak] { display: none !important; }
            .customer-topbar .profile-menu .profile-warning {
                color: #d97706 !important;
            }
            .customer-topbar .profile-menu .profile-danger {
                color: #e53935 !important;
            }
        </style>
    </head>
    <body class="min-h-screen">
        <div class="min-h-screen flex flex-col workspace">
            {{-- Top Navigation Bar --}}
            <header class="customer-topbar border-b border-muted shadow-lg sticky top-0 z-40">
                <div class="container mx-auto px-4 py-3">
                    <div class="topbar-main-row">
                        {{-- Left: Logo and System Name (same style as login) --}}
                        <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-3 hover:opacity-90 transition">
                            <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-xl bg-white flex items-center justify-center shadow-lg overflow-hidden">
                                <img src="{{ $logoUrl }}" alt="{{ config('app.system_name') }} Logo" class="h-full w-full object-contain scale-110">
                            </div>
                            <div class="hidden sm:block">
                                <p class="text-xs uppercase tracking-[0.25em] text-sky-100">
                                    {{ config('app.system_tagline', 'Loan Management System') }}
                                </p>
                                <p class="text-lg font-bold text-white leading-tight">
                                    {{ config('app.system_name') }}
                                </p>
                            </div>
                        </a>

                        {{-- Right: FAQ Link, Theme Toggle and Profile Menu --}}
                        <div class="topbar-controls">
                            <div class="topbar-primary-actions">
                                <a href="{{ route('customer.dashboard') }}" class="topbar-action-link" data-dashboard-link aria-label="Dashboard">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10.5l9-7 9 7M5.25 9.75V20.25a.75.75 0 00.75.75h4.5a.75.75 0 00.75-.75v-4.5a.75.75 0 01.75-.75h0a.75.75 0 01.75.75v4.5a.75.75 0 00.75.75H18a.75.75 0 00.75-.75V9.75"/>
                                    </svg>
                                    <span class="topbar-label">Dashboard</span>
                                </a>
                                <a href="{{ route('help.index') }}" class="topbar-action-link" data-help-link aria-label="Help">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10a4 4 0 118 0c0 1.657-1.343 3-3 3h-1v2m0 4h.01"/>
                                    </svg>
                                    <span class="topbar-label">Help</span>
                                </a>
                                <a href="{{ route('customer.notifications') }}" class="topbar-action-link" aria-label="Notifications">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/>
                                    </svg>
                                    <span class="topbar-label">Alerts</span>
                                </a>
                                <a href="{{ route('customer.faq') }}" data-faq-link class="topbar-action-link" aria-label="FAQ">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l.949-4.745A8.994 8.994 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    <span class="topbar-label">FAQ</span>
                                </a>
                            </div>

                            {{-- Profile Dropdown --}}
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="topbar-icon-btn" aria-label="Open profile menu">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                    </svg>
                                </button>
                                <div x-show="open" @click.away="open = false" x-transition class="profile-menu absolute right-0 mt-2 w-48 rounded-lg shadow-lg py-1 z-50">
                                    <a href="{{ route('customer.profile') }}" class="profile-menu-link block px-4 py-2 text-sm">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            My Profile
                                        </div>
                                    </a>
                                    <a href="{{ route('customer.statement') }}" class="profile-menu-link block px-4 py-2 text-sm">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Statement
                                        </div>
                                    </a>
                                    <a href="{{ route('customer.notifications') }}" class="profile-menu-link block px-4 py-2 text-sm">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/>
                                            </svg>
                                            Notifications
                                        </div>
                                    </a>
                                    <div class="border-t border-white/10 my-1"></div>
                                    <form method="POST" action="{{ route('customer.logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left block px-4 py-2 text-sm profile-menu-link profile-danger">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                                </svg>
                                                Logout
                                            </div>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Main Content - Two Column Layout --}}
            <main class="flex-1 flex flex-col lg:flex-row overflow-hidden">
                {{-- Left Section - Fixed Image (Desktop Only) --}}
                <aside class="hidden lg:flex lg:w-1/2 customer-aside items-center justify-center p-6" style="min-height: calc(100vh - 57px);">
                    <div class="w-full" style="max-width: 1000px;">
                        <div class="card rounded-xl overflow-hidden shadow-lg p-1">
                            <img src="{{ url('customer-view.jpg') }}" alt="Customer View" class="w-full h-auto rounded-lg" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'text-center text-gray-500 dark:text-gray-400 p-8\'>Image not found</div>';">
                        </div>
                    </div>
                </aside>

                {{-- Right Section - Dynamic Content --}}
                <div class="flex-1 lg:w-1/2 workspace overflow-y-auto pb-28 lg:pb-6">
                    <div class="container mx-auto px-4 py-6 lg:py-8">
                        {{-- Flash Messages --}}
                        @if (session('status'))
                            <div class="mb-6 card border-muted p-4 shadow" 
                                 x-data="{ show: true }" 
                                 x-show="show" 
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 transform translate-y-2"
                                 x-transition:enter-end="opacity-100 transform translate-y-0"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 x-init="setTimeout(() => show = false, 5000)">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 step-active rounded-full h-8 w-8 flex items-center justify-center text-xs font-bold">✔</div>
                                    <p class="flex-1 text-sm font-medium text-primary">{{ session('status') }}</p>
                                    <button @click="show = false" class="flex-shrink-0 text-muted hover:text-primary transition" aria-label="Close">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="mb-6 card border-muted p-4 shadow" 
                                 x-data="{ show: true }" 
                                 x-show="show" 
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 transform translate-y-2"
                                 x-transition:enter-end="opacity-100 transform translate-y-0"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 x-init="setTimeout(() => show = false, 7000)">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 step-active rounded-full h-8 w-8 flex items-center justify-center text-xs font-bold">!</div>
                                    <p class="flex-1 text-sm font-medium text-primary">{{ session('error') }}</p>
                                    <button @click="show = false" class="flex-shrink-0 text-muted hover:text-primary transition" aria-label="Close">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="mb-6 card border-muted p-4 shadow" 
                                 x-data="{ show: true }" 
                                 x-show="show" 
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 transform translate-y-2"
                                 x-transition:enter-end="opacity-100 transform translate-y-0"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 step-active rounded-full h-8 w-8 flex items-center justify-center text-xs font-bold">!</div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-primary mb-2">Please correct the following errors:</p>
                                        <ul class="list-disc list-inside space-y-1 text-sm text-muted">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <button @click="show = false" class="flex-shrink-0 text-muted hover:text-primary transition" aria-label="Close">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        @endif

                        @yield('content')
                    </div>
                </div>
            </main>

            {{-- Bottom Navigation (Mobile) --}}
            @include('customer.partials.bottom-nav', ['active' => $active ?? 'dashboard'])
        </div>
        <a href="{{ route('help.index') }}" class="floating-help-button" aria-label="Open help center" title="Help Center">?</a>
        <script src="https://cdn.jsdelivr.net/npm/intro.js/minified/intro.min.js"></script>
        <script>
            function startSystemTour() {
                if (typeof introJs !== 'function') {
                    return;
                }

                const steps = [
                    {
                        intro: 'Welcome to your dashboard.'
                    }
                ];

                const dashboardStats = document.querySelector('#dashboard-stats');
                if (dashboardStats) {
                    steps.push({
                        element: dashboardStats,
                        intro: 'Here you can review your loan status, balances, and account summary.'
                    });
                }

                const faqLink = document.querySelector('[data-faq-link]');
                if (faqLink) {
                    steps.push({
                        element: faqLink,
                        intro: 'Visit FAQ for quick answers to common questions.'
                    });
                }

                const helpButton = document.querySelector('[data-help-link]');
                if (helpButton) {
                    steps.push({
                        element: helpButton,
                        intro: 'Open Help anytime to read the full user manual.'
                    });
                }

                introJs().setOptions({
                    steps: steps,
                    nextLabel: 'Next',
                    prevLabel: 'Back',
                    doneLabel: 'Done',
                }).start();
            }

            window.startSystemTour = startSystemTour;

            window.addEventListener('DOMContentLoaded', () => {
                if (sessionStorage.getItem('startSystemTour') === '1') {
                    sessionStorage.removeItem('startSystemTour');
                    setTimeout(() => startSystemTour(), 250);
                }
            });
        </script>
        @stack('scripts')
    </body>
</html>

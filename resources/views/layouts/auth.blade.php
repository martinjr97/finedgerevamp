@php
    use Illuminate\Support\Str;

    $rawLogoPath = config('app.system_logo_path', 'img/logo.png');
    $logoUrl = Str::startsWith($rawLogoPath, ['http://', 'https://', '//'])
        ? $rawLogoPath
        : asset(ltrim($rawLogoPath, '/'));

    $rawFaviconPath = config('app.favicon_path', 'img/favicon_io/favicon.ico');
    $faviconUrl = Str::startsWith($rawFaviconPath, ['http://', 'https://', '//'])
        ? $rawFaviconPath
        : asset(ltrim($rawFaviconPath, '/'));

    $websiteUrl = config('app.website_url');
    $authSideImage = $authSideImage ?? null;
    $authSideImageUrl = null;
    if (filled($authSideImage)) {
        $authSideImageUrl = Str::startsWith($authSideImage, ['http://', 'https://', '//'])
            ? $authSideImage
            : (Str::startsWith($authSideImage, ['/','img/'])
                ? asset(ltrim($authSideImage, '/'))
                : asset('img/' . ltrim($authSideImage, '/')));
    }
    $authUseSidePanel = filled($authSideImageUrl);
    $authBackgroundImage = $backgroundImage ?? ($authUseSidePanel ? null : 'login.jpg');
    $authBackgroundUrl = filled($authBackgroundImage)
        ? (Str::startsWith($authBackgroundImage, ['http://', 'https://', '//'])
            ? $authBackgroundImage
            : (Str::startsWith($authBackgroundImage, ['/','img/'])
                ? asset(ltrim($authBackgroundImage, '/'))
                : asset('img/' . ltrim($authBackgroundImage, '/'))))
        : null;
    $authOverlayClass = $authOverlayClass ?? 'bg-white/20';
    $authHeaderClass = $authHeaderClass ?? 'from-[#151B54] via-[#151B54] to-[#151B54] border-white/10';
    $isRegistrationFlow = request()->routeIs('customer.register-request*');
    $authContainerClass = $authContainerClass ?? (
        $isRegistrationFlow
            ? 'w-full max-w-xl sm:max-w-2xl md:max-w-3xl lg:max-w-4xl xl:max-w-5xl'
            : 'max-w-sm sm:max-w-md md:max-w-lg'
    );
    $authMainItemsAlign = $authMainItemsAlign ?? ($isRegistrationFlow ? 'items-start' : 'items-center');
    $authCardClass = $authCardClass ?? 'bg-white/95 border-slate-200';
    $authCardStyle = $authCardStyle ?? '';
    $authHeadingClass = $authHeadingClass ?? 'text-slate-900';
    $authSubheadingClass = $authSubheadingClass ?? 'text-slate-600';
    $authPageClass = $authPageClass ?? 'auth-page';
    if ($authUseSidePanel && ! str_contains($authPageClass, 'customer-auth-page')) {
        $authPageClass .= ' customer-auth-page';
    }
    if ($authUseSidePanel) {
        $authOverlayClass = $authOverlayClass ?? 'bg-transparent';
        if (! $isRegistrationFlow) {
            $authContainerClass = $authContainerClass ?? 'max-w-6xl';
        }
    }
    $authBackgroundStyle = $authBackgroundStyle ?? 'background-position: center; background-size: cover; background-repeat: no-repeat;';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
	        <meta charset="utf-8">
	        <meta name="viewport" content="width=device-width, initial-scale=1">
	        <meta name="theme-color" content="#151B54">
	        <title>@yield('title', config('app.system_name'))</title>
        
        {{-- Favicons --}}
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="{{ $authPageClass }} relative isolate min-h-screen bg-slate-950 flex flex-col overflow-x-hidden">
        @if ($authBackgroundUrl)
            <div
                class="auth-page-background fixed inset-0 z-0"
                style="background-image: url('{{ $authBackgroundUrl }}'); {{ $authBackgroundStyle }}"
                aria-hidden="true"
            ></div>
        @endif
        <div class="auth-background-overlay fixed inset-0 z-10 {{ $authOverlayClass }} pointer-events-none"></div>
        
        {{-- Top Navigation --}}
        <header class="auth-topbar relative z-20 bg-gradient-to-r {{ $authHeaderClass }} shadow-lg border-b">
            <nav class="container mx-auto px-4 py-2 sm:py-2.5">
                <div class="flex items-center justify-between">
                    <a href="{{ route('customer.login') }}" class="flex items-center gap-2 sm:gap-2.5 hover:opacity-80 transition">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-lg bg-white flex items-center justify-center shadow-lg overflow-hidden">
                            <img src="{{ $logoUrl }}" alt="{{ config('app.system_name') }} Logo" class="h-full w-full object-contain scale-110">
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-primary">{{ config('app.system_name') }}</h1>
                            <p class="text-xs sm:text-sm text-muted hidden sm:block">
                                {{ config('app.system_tagline', 'Loan Management System') }}
                            </p>
                        </div>
                    </a>
                    <div class="flex items-center gap-2 sm:gap-3">
                        @if (filled($websiteUrl))
                            <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" class="auth-nav-link text-xs sm:text-sm font-medium px-3 py-1.5 rounded-lg">Visit Website</a>
                        @endif
                        @if(Route::has('customer.login') && !request()->routeIs('customer.login'))
                            <a href="{{ route('customer.login') }}" class="auth-nav-link text-xs sm:text-sm font-medium px-3 py-1.5 rounded-lg">Customer Login</a>
                        @endif
                    </div>
                </div>
            </nav>
        </header>

        {{-- Main Content Area --}}
        <main class="flex-1 flex @hasSection('auth_top') items-start @else {{ $authMainItemsAlign }} @endif justify-center py-6 sm:py-8 px-4 sm:px-6 relative z-20">
            <div class="relative w-full {{ $authContainerClass }} @if($authUseSidePanel && ! $isRegistrationFlow) lg:grid lg:grid-cols-2 lg:gap-8 xl:gap-12 lg:items-center @endif">
                @if ($authUseSidePanel && ! $isRegistrationFlow)
                    <div class="hidden lg:flex items-center justify-center auth-side-visual px-2 xl:px-4">
                        <img
                            src="{{ $authSideImageUrl }}"
                            alt="{{ config('app.system_name') }}"
                            class="w-full max-w-md xl:max-w-lg h-auto object-contain"
                        >
                    </div>
                @endif

                <div class="w-full @if($authUseSidePanel && ! $isRegistrationFlow) lg:max-w-md lg:justify-self-end xl:justify-self-center @endif">
                @hasSection('auth_top')
                    <div class="mb-4 sm:mb-5">
                        @yield('auth_top')
                    </div>
                @endif

                <div
                    class="auth-surface backdrop-blur-xl {{ $authCardClass }} border rounded-2xl shadow-2xl p-5 sm:p-6 {{ $isRegistrationFlow ? 'md:p-8 lg:p-9' : 'md:p-7' }} space-y-5"
                    @if (filled($authCardStyle)) style="{{ $authCardStyle }}" @endif
                >
                    <div class="space-y-2 text-center px-2">
                        <p class="text-xs sm:text-sm uppercase tracking-[0.2em] {{ isset($brandColor) ? $brandColor : 'text-purple-600' }} font-semibold">
                            {{ strtoupper(config('app.system_tagline', 'Loan Management System')) }}
                        </p>
                        <h1 class="text-2xl sm:text-3xl font-semibold {{ $authHeadingClass }}">@yield('heading', 'Welcome Back')</h1>
                        <p class="{{ $authSubheadingClass }} text-sm sm:text-base">@yield('subheading', 'Sign in to continue')</p>
                    </div>

                    @if(session('error'))
                        <div class="alert alert-danger" role="alert">
                            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="font-semibold">{{ session('error') }}</p>
                        </div>
                    @endif

                    @if(session('status'))
                        <div class="alert alert-info" role="status">
                            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 3a9 9 0 110 18 9 9 0 010-18z"/>
                            </svg>
                            <p class="font-semibold">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success" role="status">
                            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="font-semibold">{{ session('success') }}</p>
                        </div>
                    @endif

                    @yield('content')

                </div>
                </div>
            </div>
        </main>

        {{-- Footer --}}
        <footer class="app-chrome-footer relative z-20 border-t mt-auto">
            <div class="container mx-auto px-4 py-3 sm:py-4">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-3">
                    <div class="text-center sm:text-left">
                        <p class="text-xs sm:text-sm font-medium">{{ config('app.system_name') }}</p>
                        <p class="text-xs mt-0.5 opacity-90">© {{ date('Y') }} {{ config('app.system_name') }}. All rights reserved.</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4 text-xs opacity-90">
                        <a href="{{ route('privacy') }}" class="transition">Privacy Policy</a>
                        <a href="{{ route('terms') }}" class="transition">Terms of Service</a>
                        <a href="{{ route('faq') }}" class="transition">FAQ</a>
                        <a href="{{ route('support') }}" class="transition">Support</a>
                    </div>
                </div>
            </div>
        </footer>

        @stack('scripts')
    </body>
</html>

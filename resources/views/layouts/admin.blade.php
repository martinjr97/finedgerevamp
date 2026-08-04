@php
    use App\Support\AdminSidebarNavigation;
    use Illuminate\Support\Str;
    $currentAdmin = auth('admin')->user();
    $sidebarBrandName = strtoupper(config('app.name', 'LMS'));
    $topbarAvatarUrl = $currentAdmin && $currentAdmin->avatar_path ? asset('storage/'.$currentAdmin->avatar_path) : null;
    $topbarInitials = $currentAdmin ? strtoupper(substr($currentAdmin->first_name ?? 'A', 0, 1).substr($currentAdmin->last_name ?? 'D', 0, 1)) : 'AD';

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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
	        <meta charset="utf-8">
	        <meta name="viewport" content="width=device-width, initial-scale=1">
	        <meta name="theme-color" content="#151B54">
	        <meta name="csrf-token" content="{{ csrf_token() }}">
	        <title>@yield('title', config('app.system_name'))</title>
        
        {{-- Favicons --}}
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js/minified/introjs.min.css">
        <style>
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="min-h-screen" data-theme="light" x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false', toggleSidebar() { this.sidebarOpen = !this.sidebarOpen; localStorage.setItem('sidebarOpen', this.sidebarOpen); } }" x-init="$watch('sidebarOpen', value => localStorage.setItem('sidebarOpen', value))">
        {{-- Theme locked to light for consistent font visibility --}}
        <div class="flex min-h-screen">
            {{-- Mobile Overlay --}}
            <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50 z-40 lg:hidden" style="display: none;"></div>
            
            <aside 
                class="fixed lg:static inset-y-0 left-0 z-50 flex flex-col sidebar border-r transition-all duration-300 ease-in-out"
                :class="sidebarOpen ? 'translate-x-0 w-72' : '-translate-x-full lg:translate-x-0 lg:w-20'"
            >
                <div class="px-4 lg:px-6 py-6 flex items-center gap-3 justify-between">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="h-12 w-12 shrink-0 flex items-center justify-center overflow-hidden">
                            <img src="{{ $logoUrl }}" alt="{{ $sidebarBrandName }} logo" class="h-full w-full object-contain">
                        </div>
                        <span
                            class="font-bold text-lg tracking-wide text-white truncate transition-all duration-300"
                            :class="sidebarOpen ? 'opacity-100 max-w-[11rem]' : 'opacity-0 max-w-0 overflow-hidden lg:opacity-0 lg:max-w-0'"
                            title="{{ $sidebarBrandName }}"
                        >{{ $sidebarBrandName }}</span>
                    </div>
                    <button @click="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-white/10 transition shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 px-4 space-y-1 overflow-y-auto" aria-label="Admin navigation">
                    @php
                        // Build customer menu items
                        $customerMenuItems = [];
                        if (auth('admin')->user()?->can('customers.view')) {
                            $customerMenuItems[] = ['label' => 'View Customers', 'route' => route('admin.customers.index'), 'icon' => 'list-bullet'];
                            $customerMenuItems[] = ['label' => 'View Groups', 'route' => route('admin.customer-groups.index'), 'icon' => 'users'];
                        }
                        if (auth('admin')->user()?->can('customers.create')) {
                            $customerMenuItems[] = ['label' => 'Create Customer', 'route' => route('admin.customers.select-product-type'), 'icon' => 'plus-circle'];
                        }
                        if (auth('admin')->user()?->can('customer-requests.view')) {
                            $customerMenuItems[] = ['label' => 'Customer Requests', 'route' => route('admin.customer-requests.index'), 'icon' => 'document-text'];
                        }
                        if (auth('admin')->user()?->can('fraud-protection.view')) {
                            $customerMenuItems[] = ['label' => 'Fraud & Abuse Protection', 'route' => route('admin.fraud-protection.index'), 'icon' => 'shield-exclamation'];
                        }
                        
                        $navItems = [
                            [
                                'label' => 'Dashboard',
                                'icon' => 'chart-bar',
                                'id' => 'dashboard',
                                'route' => route('admin.dashboard'),
                                'permission' => true, // Dashboard is accessible to all authenticated admins
                            ],
                            [
                                'label' => 'Users',
                                'icon' => 'users',
                                'id' => 'menu-users',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('admins.view') ? ['label' => 'View Users', 'route' => route('admin.users.index'), 'icon' => 'list-bullet'] : null,
                                    auth('admin')->user()?->can('admins.create') ? ['label' => 'Create User', 'route' => route('admin.users.create'), 'icon' => 'plus-circle'] : null,
                                ]),
                            ],
                            [
                                'label' => 'Companies',
                                'icon' => 'building-office',
                                'id' => 'menu-companies',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('companies.view') ? ['label' => 'View Companies', 'route' => route('admin.companies.index'), 'icon' => 'list-bullet'] : null,
                                    auth('admin')->user()?->can('companies.create') ? ['label' => 'Register Company', 'route' => route('admin.companies.create'), 'icon' => 'plus-circle'] : null,
                                ]),
                            ],
                            [
                                'label' => 'Customers',
                                'icon' => 'user-group',
                                'id' => 'menu-customers',
                                'children' => $customerMenuItems,
                            ],
                            [
                                'label' => 'Communications',
                                'icon' => 'chat-bubble-left-right',
                                'id' => 'menu-communications',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('communications.view') ? ['label' => 'View Communications', 'route' => route('admin.communications.index'), 'icon' => 'list-bullet'] : null,
                                    auth('admin')->user()?->can('communications.create') ? ['label' => 'Send Communication', 'route' => route('admin.communications.create'), 'icon' => 'paper-airplane'] : null,
                                ]),
                            ],
                            [
                                'label' => 'Support Tickets',
                                'icon' => 'lifebuoy',
                                'id' => 'menu-support-tickets',
                                'route' => route('admin.support-tickets.index'),
                                'permission' => true,
                            ],
                            [
                                'label' => 'Loan Management',
                                'icon' => 'currency-dollar',
                                'id' => 'menu-loan-management',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('loans.view') ? ['label' => 'View Loans', 'route' => route('admin.loans.index'), 'icon' => 'list-bullet'] : null,
                                    auth('admin')->user()?->can('repayments.view') ? ['label' => 'Repayments', 'route' => route('admin.repayments.index'), 'icon' => 'credit-card'] : null,
                                    auth('admin')->user()?->can('bulk-repayments.view') ? ['label' => 'Bulk Repayment', 'route' => route('admin.bulk-repayments.index'), 'icon' => 'arrow-up-tray'] : null,
                                    auth('admin')->user()?->can('pmec_submissions.view') ? ['label' => 'PMEC Submissions', 'route' => route('admin.pmec-submissions.index'), 'icon' => 'document-arrow-up'] : null,
                                    auth('admin')->user()?->can('loan-applications.view') ? ['label' => 'Loan Application', 'route' => route('admin.loan-applications.index'), 'icon' => 'document-plus'] : null,
                                    auth('admin')->user()?->can('loans.view') ? ['label' => 'Group Loan Applications', 'route' => route('admin.loan-applications.group-loans.index'), 'icon' => 'users'] : null,
                                    auth('admin')->user()?->can('loans.view') ? ['label' => 'Loan Calculator', 'route' => route('admin.loan-calculator.index'), 'icon' => 'calculator'] : null,
                                    auth('admin')->user()?->can('loans.view') ? ['label' => 'Payment Due Report', 'route' => route('admin.payment-due-report.select'), 'icon' => 'document-text'] : null,
                                    // Future: ['label' => 'Migrate Loan', 'route' => route('admin.loans.migrate'), 'icon' => 'arrow-right'] : null,
                                ]),
                            ],
                            [
                                'label' => 'Financial Management',
                                'icon' => 'banknotes',
                                'id' => 'menu-financial-management',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('banks.view') ? ['label' => 'Banks', 'route' => route('admin.banks.index'), 'icon' => 'building-library'] : null,
                                    auth('admin')->user()?->can('wallets.view') ? ['label' => 'Wallets', 'route' => route('admin.wallets.index'), 'icon' => 'device-phone-mobile'] : null,
                                    auth('admin')->user()?->can('creditors.view') ? ['label' => 'Creditors', 'route' => route('admin.creditors.index'), 'icon' => 'document-text'] : null,
                                    auth('admin')->user()?->can('financial-transactions.view') ? ['label' => 'Transactions', 'route' => route('admin.financial-transactions.index'), 'icon' => 'arrows-right-left'] : null,
                                    auth('admin')->user()?->can('transfers.view') ? ['label' => 'Transfers', 'route' => route('admin.transfers.index'), 'icon' => 'arrow-path'] : null,
                                    auth('admin')->user()?->can('financial-statements.view') ? ['label' => 'Balance Sheet', 'route' => route('admin.financial-statements.balance-sheet'), 'icon' => 'document-text'] : null,
                                    auth('admin')->user()?->can('financial-statements.view') ? ['label' => 'Cash Flow', 'route' => route('admin.financial-statements.cash-flow'), 'icon' => 'arrow-trending-up'] : null,
                                    auth('admin')->user()?->can('financial-statements.view') ? ['label' => 'Income Statement', 'route' => route('admin.financial-statements.income-statement'), 'icon' => 'chart-bar'] : null,
                                ]),
                            ],
                            [
                                'label' => 'Roles & Permissions',
                                'icon' => 'key',
                                'id' => 'menu-roles',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('roles.view') ? ['label' => 'View Roles', 'route' => route('admin.roles.index'), 'icon' => 'list-bullet'] : null,
                                    auth('admin')->user()?->can('roles.create') ? ['label' => 'Create Role', 'route' => route('admin.roles.create'), 'icon' => 'plus-circle'] : null,
                                    auth('admin')->user()?->can('permissions.view') ? ['label' => 'Permissions Matrix', 'route' => route('admin.roles.index'), 'icon' => 'table-cells'] : null,
                                ]),
                            ],
                            [
                                'label' => 'Configurations',
                                'icon' => 'cog',
                                'id' => 'menu-configurations',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('loan-products.view') ? ['label' => 'Product Types', 'route' => route('admin.loan-products.index'), 'permission' => 'loan-products.view', 'icon' => 'cube'] : null,
                                    ($firstProduct = \App\Models\LoanProduct::first()) && auth('admin')->user()?->can('loan-products.view') ? ['label' => 'Collateral Types', 'route' => route('admin.loan-products.collateral-types.index', $firstProduct), 'permission' => 'loan-products.view', 'icon' => 'table-cells'] : null,
                                    auth('admin')->user()?->can('loan-rate-types.view') ? ['label' => 'Interest Rate Types', 'route' => route('admin.loan-rate-types.index'), 'permission' => 'loan-rate-types.view', 'icon' => 'currency-dollar'] : null,
                                    auth('admin')->user()?->can('sectors.view') ? ['label' => 'Sectors', 'route' => route('admin.sectors.index'), 'permission' => 'sectors.view', 'icon' => 'building-office-2'] : null,
                                    auth('admin')->user()?->can('ministries.view') ? ['label' => 'Ministries', 'route' => route('admin.ministries.index'), 'permission' => 'ministries.view', 'icon' => 'building-office'] : null,
                                    auth('admin')->user()?->can('loan-purposes.view') ? ['label' => 'Loan Purposes', 'route' => route('admin.loan-purposes.index'), 'permission' => 'loan-purposes.view', 'icon' => 'clipboard-document-list'] : null,
                                    auth('admin')->user()?->can('provinces.view') ? ['label' => 'Provinces', 'route' => route('admin.provinces.index'), 'permission' => 'provinces.view', 'icon' => 'map'] : null,
                                    auth('admin')->user()?->can('branches.view') ? ['label' => 'Branches', 'route' => route('admin.branches.index'), 'permission' => 'branches.view', 'icon' => 'building-office'] : null,
                                    auth('admin')->user()?->can('settings.view') ? ['label' => 'General Settings', 'route' => route('admin.settings.general.edit'), 'permission' => 'settings.view', 'icon' => 'cog'] : null,
	                                    auth('admin')->user()?->can('security-questions.view') ? ['label' => 'Security Questions', 'route' => route('admin.security-questions.index'), 'permission' => 'security-questions.view', 'icon' => 'question-mark-circle'] : null,
	                                    auth('admin')->user()?->can('financial-institutions.view') ? ['label' => 'Banking Institutions', 'route' => route('admin.financial-institutions.index'), 'permission' => 'financial-institutions.view', 'icon' => 'building-office'] : null,
	                                    (auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')) ? ['label' => 'Payment Gateways', 'route' => route('admin.payment-gateways.index'), 'permission' => 'payment-gateways.view', 'icon' => 'credit-card'] : null,
	                                    (auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')) ? ['label' => 'Destination Mappings', 'route' => route('admin.payment-gateway-destination-mappings.index'), 'permission' => 'payment-gateways.view', 'icon' => 'map'] : null,
	                                    (auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')) ? ['label' => 'Gateway Routing', 'route' => route('admin.payment-gateway-routing.index'), 'permission' => 'payment-gateways.view', 'icon' => 'arrows-right-left'] : null,
	                                    (auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')) ? ['label' => 'Payment Operations', 'route' => route('admin.payment-operations.index'), 'permission' => 'payment-gateways.view', 'icon' => 'signal'] : null,
	                                    (auth('admin')->user()?->can('payment-gateways.view') || auth('admin')->user()?->can('payment-gateways.manage')) ? ['label' => 'Failed Financial Jobs', 'route' => route('admin.payment-operations.failed-jobs.index'), 'permission' => 'payment-gateways.view', 'icon' => 'exclamation-triangle'] : null,
	                                    (auth('admin')->user()?->can('sms-operations.view') || auth('admin')->user()?->can('sms-operations.manage')) ? ['label' => 'SMS Operations', 'route' => route('admin.sms-operations.index'), 'permission' => 'sms-operations.view', 'icon' => 'chat-bubble-left-right'] : null,
	                                    auth('admin')->user()?->can('channels.view') ? ['label' => 'Payment Channels', 'route' => route('admin.channels.index'), 'permission' => 'channels.view', 'icon' => 'credit-card'] : null,
	                                    auth('admin')->user()?->can('wallet-providers.view') ? ['label' => 'Wallet Providers', 'route' => route('admin.wallet-providers.index'), 'permission' => 'wallet-providers.view', 'icon' => 'device-phone-mobile'] : null,
	                                    ['label' => 'FAQs', 'route' => route('admin.faqs.index'), 'icon' => 'question-mark-circle'],
	                                ]),
                            ],
                        ];
                        
                        // Add Approvals menu item if user has permission
                        if (auth('admin')->user()?->can('approvals.view')) {
                            $navItems[] = [
                                'label' => 'Approvals',
                                'icon' => 'check-circle',
                                'id' => 'menu-approvals',
                                'route' => route('admin.approvals.index'),
                            ];
                        }
                        
                        // Add Reports menu item if user has permission
                        if (auth('admin')->user()?->can('reports.view')) {
                            $navItems[] = [
                                'label' => 'Reports',
                                'icon' => 'document-chart-bar',
                                'id' => 'menu-reports',
                                'children' => array_filter([
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Arrears Report', 'route' => route('admin.reports.arrears'), 'icon' => 'exclamation-triangle'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Disbursements Report', 'route' => route('admin.reports.disbursements'), 'icon' => 'arrow-down-tray'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Collections Report', 'route' => route('admin.reports.collections'), 'icon' => 'currency-dollar'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Collection Split', 'route' => route('admin.reports.collection-split'), 'icon' => 'scissors'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Loan Book Report', 'route' => route('admin.reports.loan-book'), 'icon' => 'book-open'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Loan Performance', 'route' => route('admin.reports.loan-performance'), 'icon' => 'chart-bar'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Branch Report', 'route' => route('admin.reports.branches'), 'icon' => 'building-office-2'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Risk Heatmap Dashboard', 'route' => route('admin.reports.risk-heatmap'), 'icon' => 'fire'] : null,
                                    auth('admin')->user()?->can('reports.view') ? ['label' => 'Relationship Manager Report', 'route' => route('admin.reports.relationship-manager'), 'icon' => 'users'] : null,
                                ]),
                            ];
                        }

                        if (auth('admin')->user()?->can('audit-logs.view')) {
                            $navItems[] = [
                                'label' => 'Audit Logs',
                                'icon' => 'document',
                                'id' => 'menu-audit-logs',
                                'route' => route('admin.audit-logs.index'),
                            ];
                        }

                        if (auth('admin')->user()?->can('backups.view')) {
                            $navItems[] = [
                                'label' => 'Backups',
                                'icon' => 'archive-box',
                                'id' => 'menu-backups',
                                'route' => route('admin.backups.index'),
                            ];
                        }

                        $navItems = AdminSidebarNavigation::applyRoutePatterns($navItems);
                    @endphp
                    @foreach ($navItems as $item)
                        @php
                            $hasChildren = !empty($item['children']) && count($item['children']) > 0;
                            // If permission is set to true, always show. If set to a permission string, check it. If not set, show.
                            $hasPermission = !isset($item['permission']) || $item['permission'] === true || auth('admin')->user()?->can($item['permission']);
                            $menuGroupActive = AdminSidebarNavigation::itemIsActive($item);
                            $menuGroupOpen = AdminSidebarNavigation::groupIsOpen($item);
                        @endphp
                        @if($hasPermission)
                        <div class="py-1 border-b border-white/10 last:border-b-0">
                            @if ($hasChildren)
                                <details class="group menu-group" @if($menuGroupOpen) open @endif>
                                    <summary
                                        @if(!empty($item['id'])) id="{{ $item['id'] }}" @endif
                                        class="flex items-center gap-3 px-4 py-3 rounded-2xl text-lg font-bold text-white hover:bg-white/10 transition cursor-pointer list-none relative group {{ $menuGroupActive ? 'menu-item-active menu-group-open' : '' }}"
                                        :title="!sidebarOpen ? '{{ $item['label'] }}' : ''"
                                    >
                                        @include('partials.admin.icon', ['name' => $item['icon']])
                                        <span class="flex-1 transition-all duration-300 whitespace-nowrap" :class="sidebarOpen ? 'opacity-100 w-auto' : 'opacity-0 w-0 lg:opacity-0 lg:w-0 overflow-hidden'">{{ $item['label'] }}</span>
                                        <svg class="w-3 h-3 text-white group-open:rotate-180 transition-all duration-300 flex-shrink-0" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 lg:opacity-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 9l6 6 6-6"/>
                                        </svg>
                                        <div x-show="!sidebarOpen" class="hidden lg:block absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-sm rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50">
                                            {{ $item['label'] }}
                                        </div>
                                    </summary>
                                    <div class="mt-1 space-y-1 transition-all duration-300 submenu-panel" :class="sidebarOpen ? 'opacity-100 max-h-[min(32rem,70vh)] overflow-y-auto pl-8 ml-8 border-l-[3px] border-white/20 rounded-xl' : 'opacity-0 max-h-0 overflow-hidden lg:opacity-0 lg:max-h-0'">
                                        @foreach ($item['children'] as $child)
                                            @php $childIsActive = AdminSidebarNavigation::childIsActive($child); @endphp
                                            <a href="{{ $child['route'] }}" class="flex items-center gap-2 pl-6 pr-3 py-2 rounded-xl text-sm text-white hover:bg-white/10 transition relative group {{ $childIsActive ? 'submenu-item-active' : '' }}" :title="!sidebarOpen ? '{{ $child['label'] }}' : ''" @if($childIsActive) aria-current="page" @endif>
                                                @if(isset($child['icon']))
                                                    @include('partials.admin.icon', ['name' => $child['icon'], 'size' => 'w-4 h-4', 'color' => 'text-white'])
                                                @endif
                                                <span class="transition-all duration-300 whitespace-nowrap" :class="sidebarOpen ? 'opacity-100 w-auto' : 'opacity-0 w-0 lg:opacity-0 lg:w-0 overflow-hidden'">{{ $child['label'] }}</span>
                                                <div x-show="!sidebarOpen" class="hidden lg:block absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-sm rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50">
                                                    {{ $child['label'] }}
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            @elseif (isset($item['route']))
                                <a
                                    href="{{ $item['route'] }}"
                                    @if(!empty($item['id'])) id="{{ $item['id'] }}" @endif
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl text-lg font-bold text-white hover:bg-white/10 transition relative group {{ $menuGroupActive ? 'menu-item-active' : '' }}"
                                    :title="!sidebarOpen ? '{{ $item['label'] }}' : ''"
                                    @if($menuGroupActive) aria-current="page" @endif
                                >
                                    @include('partials.admin.icon', ['name' => $item['icon']])
                                    <span class="transition-all duration-300 whitespace-nowrap" :class="sidebarOpen ? 'opacity-100 w-auto' : 'opacity-0 w-0 lg:opacity-0 lg:w-0 overflow-hidden'">{{ $item['label'] }}</span>
                                    <div x-show="!sidebarOpen" class="hidden lg:block absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-sm rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50">
                                        {{ $item['label'] }}
                                    </div>
                                </a>
                            @endif
                        </div>
                        @endif
                    @endforeach
                </nav>
                <div class="px-4 py-6 text-xs text-white/80 transition-opacity duration-300" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 lg:opacity-0'">
                    <span :class="sidebarOpen ? '' : 'hidden lg:hidden'">{{ now()->format('M d, Y') }}</span>
                </div>
            </aside>

            <div class="flex-1 flex flex-col min-h-screen min-w-0 lg:transition-all lg:duration-300" :class="sidebarOpen ? 'lg:ml-0' : 'lg:ml-0'">
                <header class="border-b px-4 lg:px-10 py-3 flex items-center justify-between topbar sticky top-0 z-30">
                    <div class="flex items-center gap-3 flex-1">
                        <button @click="toggleSidebar()" class="topbar-icon-btn lg:hidden" aria-label="Toggle sidebar">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <button @click="toggleSidebar()" class="hidden lg:flex topbar-icon-btn" aria-label="Toggle sidebar">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div class="text-left">
                            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">{{ config('app.system_name') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-4 min-w-0 shrink">
                        <a href="{{ route('help.index') }}" class="topbar-action-link hidden sm:inline-flex" data-help-link>
                            Help
                        </a>
                        <button type="button" class="topbar-action-link hidden sm:inline-flex" onclick="startSystemTour()">
                            Start Tour
                        </button>
                        <details class="relative group">
                            <summary class="flex items-center gap-2 sm:gap-3 cursor-pointer list-none min-w-0 max-w-[10.5rem] sm:max-w-none sm:min-w-[230px] overflow-hidden">
                                <div class="h-10 w-10 rounded-full border border-white/10 flex-shrink-0 flex items-center justify-center">
                                    @if ($topbarAvatarUrl)
                                        <img src="{{ $topbarAvatarUrl }}" alt="Admin avatar" class="h-full w-full rounded-full object-cover">
                                    @else
                                        <span class="text-sm font-semibold text-cyan-300">{{ $topbarInitials }}</span>
                                    @endif
                                </div>
                                <div class="text-left min-w-0 flex-1 overflow-hidden">
                                    <p class="text-sm font-semibold truncate">
                                        {{ $currentAdmin?->full_name ?? 'Admin User' }}
                                    </p>
                                    <p class="text-xs text-slate-400 truncate">
                                        {{ $currentAdmin?->roles->pluck('name')->join(', ') ?: 'Role' }}
                                    </p>
                                </div>
                                <svg class="w-3 h-3 text-slate-400 flex-shrink-0 group-open:rotate-180 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 9l6 6 6-6"/>
                                </svg>
                            </summary>
                            <div class="absolute right-0 mt-3 w-48 rounded-2xl topbar-menu shadow-2xl py-2">
                                <a href="{{ route('admin.profile.show') }}" class="block px-4 py-2 text-sm topbar-menu-link">Manage Profile</a>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm topbar-menu-link text-rose-200 hover:text-white">
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                </header>

                <main class="flex-1 min-w-0 px-6 lg:px-12 py-10 content-area">
                    @include('partials.admin.flash')
                    @yield('content')
                </main>

                <footer class="app-chrome-footer border-t px-4 lg:px-10 py-4 mt-auto">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4">
                        <div class="text-center sm:text-left">
                            <p class="text-sm font-medium">{{ config('app.system_name') }}</p>
                            <p class="text-xs mt-1">© {{ date('Y') }} {{ config('app.system_name') }}. All rights reserved.</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-xs sm:text-sm">
                            <span>Version {{ config('app.version', '1.0.0') }}</span>
                            <span>•</span>
                            <span>{{ now()->format('l, F j, Y') }}</span>
                        </div>
                    </div>
                </footer>
            </div>
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
                        intro: 'Welcome to the system dashboard.'
                    }
                ];

                const dashboardStats = document.querySelector('#dashboard-stats');
                if (dashboardStats) {
                    steps.push({
                        element: dashboardStats,
                        intro: 'Here you can see system statistics and performance summaries.'
                    });
                }

                const usersMenu = document.querySelector('#menu-users');
                if (usersMenu) {
                    steps.push({
                        element: usersMenu,
                        intro: 'Manage admin users and roles here. Creating new admins requires the correct permission.'
                    });
                }

                const customersMenu = document.querySelector('#menu-customers');
                if (customersMenu) {
                    steps.push({
                        element: customersMenu,
                        intro: 'Manage customer records here. Creating customers by product type is shown when you have create permission.'
                    });
                }

                const companiesMenu = document.querySelector('#menu-companies');
                if (companiesMenu) {
                    steps.push({
                        element: companiesMenu,
                        intro: 'Create and configure partner companies here before onboarding MOU and SME customers linked to those companies.'
                    });
                }

                const communicationsMenu = document.querySelector('#menu-communications');
                if (communicationsMenu) {
                    steps.push({
                        element: communicationsMenu,
                        intro: 'Use Communications to send SMS or email notifications to filtered customer groups.'
                    });
                }

                const supportTicketsMenu = document.querySelector('#menu-support-tickets');
                if (supportTicketsMenu) {
                    steps.push({
                        element: supportTicketsMenu,
                        intro: 'Support Tickets is where customer-submitted issues are reviewed, updated, and resolved.'
                    });
                }

                const loanManagementMenu = document.querySelector('#menu-loan-management');
                if (loanManagementMenu) {
                    steps.push({
                        element: loanManagementMenu,
                        intro: 'Use Loan Management for loan operations. Applying on behalf of customers requires loan create permission.'
                    });
                }

                const approvalsMenu = document.querySelector('#menu-approvals');
                if (approvalsMenu) {
                    steps.push({
                        element: approvalsMenu,
                        intro: 'Approve or reject pending customers, loans, and other approval items here.'
                    });
                }

                const reportsMenu = document.querySelector('#menu-reports');
                if (reportsMenu) {
                    steps.push({
                        element: reportsMenu,
                        intro: 'Open Reports to generate and extract arrears, disbursement, collections, and loan performance exports.'
                    });
                }

                const configurationsMenu = document.querySelector('#menu-configurations');
                if (configurationsMenu) {
                    steps.push({
                        element: configurationsMenu,
                        intro: 'All major setup and configuration areas are grouped in this menu.'
                    });
                }

                const financialManagementMenu = document.querySelector('#menu-financial-management');
                if (financialManagementMenu) {
                    steps.push({
                        element: financialManagementMenu,
                        intro: 'Manage banks, wallets, transfers, transactions, and financial statements here.'
                    });
                }

                const helpButton = document.querySelector('[data-help-link]');
                if (helpButton) {
                    steps.push({
                        element: helpButton,
                        intro: 'Use Help anytime to open the user manual and workflow guidance.'
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
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0"></script>
        <script>
            (() => {
                const body = document.body;
                const toggle = document.getElementById('theme-toggle');
                const icon = document.getElementById('theme-icon');
                const stored = 'light'; /* always light for readable font colors */
                setTheme(stored);

                toggle?.addEventListener('click', () => {
                    const next = body.dataset.theme === 'dark' ? 'light' : 'dark';
                    setTheme(next);
                });

                function setTheme(theme) {
                    body.dataset.theme = theme;
                    localStorage.setItem('admin-theme', theme);
                    if (icon) {
                        icon.innerHTML = theme === 'light'
                            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m6.364.386-1.59 1.59M21 12h-2.25m-.386 6.364-1.59-1.59M12 18.75V21m-4.364-2.386 1.59-1.59M5.25 12H3m2.386-4.364 1.59 1.59M12 7.5a4.5 4.5 0 100 9 4.5 4.5 0 000-9z"/>'
                            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7.5 7.5 0 0021 12.79z"/>';
                    }
                }
            })();
        </script>
        <script>
            (function() {
                function initAdminDataTables() {
                    if (typeof window.simpleDatatables === 'undefined') {
                        setTimeout(initAdminDataTables, 100);
                        return;
                    }

                    const getBoolean = (value, fallback = true) => {
                        if (value === undefined) return fallback;
                        if (typeof value === 'boolean') return value;
                        return value !== 'false';
                    };

                    const qs = (root, selectors) => {
                        for (const selector of selectors) {
                            const el = root.querySelector(selector);
                            if (el) return el;
                        }
                        return null;
                    };

                    document.querySelectorAll('[data-datatable]').forEach((table) => {
                        if (table.dataset.datatableInit === 'true' || table.dataset.datatableInit === 'skipped-empty') {
                            return;
                        }

                        const adminTableRoot = table.closest('.admin-data-table');
                        const isAdminDataTable = Boolean(adminTableRoot);

                        // simple-datatables v9 throws on a single colspan empty-state row.
                        const tbody = table.querySelector('tbody');
                        const bodyRows = tbody ? Array.from(tbody.querySelectorAll(':scope > tr')) : [];
                        const isEmptyPlaceholder = bodyRows.length === 0 || bodyRows.every((row) => {
                            const cells = Array.from(row.querySelectorAll(':scope > td'));
                            return cells.length === 1 && cells[0].hasAttribute('colspan');
                        });

                        if (isEmptyPlaceholder) {
                            const emptyMessage = bodyRows[0]?.querySelector('td')?.textContent?.trim()
                                || 'No records to display';
                            table.dataset.datatableInit = 'skipped-empty';

                            if (isAdminDataTable) {
                                const empty = document.createElement('div');
                                empty.className = 'admin-data-table__empty';
                                empty.setAttribute('role', 'status');
                                empty.textContent = emptyMessage;
                                table.replaceWith(empty);
                                return;
                            }

                            bodyRows.forEach((row) => row.remove());
                        }

                        const perPage = parseInt(table.dataset.datatablePerPage ?? '10', 10);
                        const perPageSelect = (table.dataset.datatablePerPageSelect ?? '10,25,50,100')
                            .split(',')
                            .map((value) => parseInt(value.trim(), 10))
                            .filter(Number.isFinite);

                        const headersBeforeInit = table.querySelectorAll('thead th');
                        const columnOptions = [];

                        headersBeforeInit.forEach((header, index) => {
                            const headerText = header.textContent.trim().toLowerCase();
                            const isActions = headerText === 'actions' || headerText === 'action';
                            const isCheckboxOnly = Boolean(header.querySelector('input[type="checkbox"]'))
                                && headerText.replace(/\s+/g, '') === '';
                            const explicitlyDisabled = header.dataset.sortable === 'false';

                            if (isActions || isCheckboxOnly || explicitlyDisabled) {
                                columnOptions.push({ select: index, sortable: false });
                            }
                        });

                        const searchPlaceholder = table.dataset.datatableSearchPlaceholder || 'Search records…';

                        // simple-datatables v9 renders: <select> + labels.perPage (no {select} token).
                        const options = {
                            searchable: getBoolean(table.dataset.datatableSearch, true),
                            sortable: true,
                            fixedHeight: getBoolean(table.dataset.datatableFixedHeight, false),
                            perPage,
                            perPageSelect,
                            columns: columnOptions,
                            labels: {
                                placeholder: searchPlaceholder,
                                perPage: isAdminDataTable ? 'entries' : 'entries per page',
                                noRows: 'No records to display',
                                info: 'Showing {start} to {end} of {rows} entries',
                            },
                        };

                        try {
                            const instance = new simpleDatatables.DataTable(table, options);
                            table.dataset.datatableInit = 'true';
                            table.__dataTable = instance;

                            if (!isAdminDataTable) {
                                return;
                            }

                            const wrapper = qs(adminTableRoot, ['.datatable-wrapper', '.dataTable-wrapper']);
                            if (!wrapper) {
                                return;
                            }

                            const top = qs(wrapper, ['.datatable-top', '.dataTable-top']);
                            if (!top || top.dataset.adminToolbarReady === 'true') {
                                return;
                            }

                            // Build an explicit flex toolbar so plugin floats/block wrappers cannot stack controls.
                            const toolbar = document.createElement('div');
                            toolbar.className = 'admin-table-toolbar';

                            const left = document.createElement('div');
                            left.className = 'admin-table-toolbar__left';

                            const right = document.createElement('div');
                            right.className = 'admin-table-toolbar__right';

                            const dropdown = qs(top, ['.datatable-dropdown', '.dataTable-dropdown']);
                            const search = qs(top, ['.datatable-search', '.dataTable-search']);
                            const bulk = adminTableRoot.querySelector('[data-admin-table-bulk]');

                            if (dropdown) {
                                dropdown.classList.add('admin-table-length');
                                const label = dropdown.querySelector('label');
                                const selector = qs(dropdown, ['.datatable-selector', '.dataTable-selector', 'select']);
                                if (label && selector) {
                                    label.replaceChildren();
                                    const showText = document.createElement('span');
                                    showText.textContent = 'Show';
                                    const entriesText = document.createElement('span');
                                    entriesText.textContent = 'entries';
                                    label.append(showText, selector, entriesText);
                                    label.classList.add('admin-table-length__label');
                                }
                                if (selector) {
                                    selector.setAttribute('aria-label', 'Rows per page');
                                }
                                left.appendChild(dropdown);
                            }

                            if (bulk) {
                                bulk.classList.add('admin-table-bulk-actions');
                                if (!bulk.hasAttribute('hidden')) {
                                    bulk.hidden = true;
                                    bulk.setAttribute('aria-hidden', 'true');
                                }
                                left.appendChild(bulk);
                            }

                            if (search) {
                                right.appendChild(search);
                            }

                            toolbar.append(left, right);
                            top.replaceChildren(toolbar);
                            top.dataset.adminToolbarReady = 'true';

                            const searchInput = qs(toolbar, ['.datatable-input', '.dataTable-input', 'input[type="search"]', 'input']);
                            if (searchInput) {
                                searchInput.setAttribute('aria-label', searchPlaceholder);
                                searchInput.setAttribute('placeholder', searchPlaceholder);
                                if (!searchInput.id) {
                                    searchInput.id = 'admin-data-table-search-' + Math.random().toString(36).slice(2, 8);
                                }
                            }

                            wrapper.querySelectorAll('thead th').forEach((header) => {
                                if (header.dataset.sortable === 'false' || header.querySelector('input[type="checkbox"]')) {
                                    return;
                                }
                                const text = header.textContent.trim().toLowerCase();
                                if (text === 'actions' || text === 'action') {
                                    return;
                                }
                                if (header.querySelector('.sort-indicator')) {
                                    return;
                                }

                                const indicator = document.createElement('span');
                                indicator.className = 'sort-indicator';
                                indicator.setAttribute('aria-hidden', 'true');
                                indicator.innerHTML = '<span class="sort-up">▲</span><span class="sort-down">▼</span>';
                                header.appendChild(indicator);
                            });
                        } catch (error) {
                            console.error('Failed to initialize DataTable:', error?.message || error);
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initAdminDataTables);
                } else {
                    initAdminDataTables();
                }
                window.addEventListener('load', initAdminDataTables);
            })();
        </script>
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
        <script>
            (() => {
                const MIN_OPTIONS_FOR_SEARCH = 6;

                const shouldEnhanceSelect = (select) => {
                    if (!select || select.dataset.selectSearchInit === 'true' || select.tomselect) {
                        return false;
                    }

                    if (select.dataset.noSelectSearch === 'true' || select.dataset.noSelectFilter === 'true') {
                        return false;
                    }

                    if (select.multiple || select.disabled) {
                        return false;
                    }

                    if (
                        select.closest('.dataTable-wrapper, .datatable-wrapper') ||
                        select.closest('.dataTable-top, .datatable-top') ||
                        select.closest('.dataTable-pagination, .datatable-pagination') ||
                        select.closest('.admin-table-toolbar, .admin-table-bulk-actions') ||
                        select.closest('[data-admin-table-bulk]') ||
                        select.closest('[data-no-select-filter]')
                    ) {
                        return false;
                    }

                    const optionsCount = Array.from(select.options).filter((option) => option.textContent.trim() !== '').length;

                    return optionsCount >= MIN_OPTIONS_FOR_SEARCH;
                };

                const initSearchableSelect = (select) => {
                    const placeholderOption = select.querySelector('option[value=""]');
                    const placeholder = select.dataset.searchPlaceholder
                        || placeholderOption?.textContent?.trim()
                        || 'Type to search...';

                    const control = new TomSelect(select, {
                        create: false,
                        maxItems: 1,
                        allowEmptyOption: true,
                        searchField: ['text'],
                        closeAfterSelect: true,
                        placeholder,
                        onItemAdd() {
                            this.close();
                        },
                        render: {
                            no_results(data, escape) {
                                return `<div class="no-results">No matches for "${escape(data.input)}"</div>`;
                            },
                        },
                    });

                    select.dataset.selectSearchInit = 'true';

                    if (select.form) {
                        select.form.addEventListener('reset', () => {
                            window.setTimeout(() => {
                                control.clear(true);
                                control.sync();
                            }, 0);
                        });
                    }
                };

                const initSearchableSelects = () => {
                    if (typeof window.TomSelect === 'undefined') {
                        return;
                    }

                    document.querySelectorAll('main select').forEach((select) => {
                        if (shouldEnhanceSelect(select)) {
                            initSearchableSelect(select);
                        }
                    });
                };

                const syncEnhancedSelects = () => {
                    document.querySelectorAll('main select[data-select-search-init="true"]').forEach((select) => {
                        if (select.tomselect) {
                            select.tomselect.sync();
                        }
                    });
                };

                let syncTimer = null;
                const queueSyncEnhancedSelects = () => {
                    if (syncTimer) {
                        window.clearTimeout(syncTimer);
                    }
                    syncTimer = window.setTimeout(() => {
                        syncEnhancedSelects();
                        syncTimer = null;
                    }, 0);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initSearchableSelects);
                } else {
                    initSearchableSelects();
                }

                window.addEventListener('load', initSearchableSelects);
                document.addEventListener('change', queueSyncEnhancedSelects, true);
            })();
        </script>
        @stack('scripts')
    </body>
</html>

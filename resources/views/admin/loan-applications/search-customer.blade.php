@extends('layouts.admin')

@section('title', 'Search Customer | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Search Customer',
            'description' => 'Find customer by phone number, NRC, or name',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back',
                    'href' => route('admin.loan-applications.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        {{-- Step Indicator --}}
        <div class="flex items-center justify-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white font-semibold">✓</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Product Selected</span>
                </div>
                <div class="h-1 w-16 bg-cyan-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500 text-white font-semibold">2</div>
                    <span class="ml-2 text-sm font-medium text-white">Search Customer</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">3</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Loan Details</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">4</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">
                        {{ (isset($flowType) && in_array($flowType, ['mou', 'character', 'government', 'sme'])) ? 'Review' : 'Collateral' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Product Info --}}
        <div class="rounded-3xl border border-cyan-500/30 bg-cyan-950/30 p-4 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Selected Product</p>
                    <p class="text-lg font-semibold text-white">{{ $loanProduct->name }}</p>
                </div>
                <span class="rounded-full bg-cyan-500/20 px-3 py-1 text-sm text-cyan-300">
                    {{ strtoupper($loanProduct->code) }}
                </span>
            </div>
        </div>

        {{-- Customer Search --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-cyan-500"></span>Search Customer
            </h2>
            
            <form id="customerSearchForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Search by Phone, NRC, or Name</label>
                    <input type="text" 
                           id="customerSearch" 
                           name="search" 
                           placeholder="Enter phone number, NRC, or customer name..."
                           class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                           autocomplete="off">
                    <p class="mt-2 text-xs text-slate-400">Start typing to search for customers...</p>
                </div>
            </form>

            {{-- Search Results --}}
            <div id="searchResults" class="mt-6 hidden">
                <h3 class="mb-4 text-sm font-semibold text-white">Search Results</h3>
                <div id="customersList" class="space-y-3"></div>
            </div>

            {{-- Selected Customer Info --}}
            <div id="selectedCustomer" class="mt-6 hidden">
                <h3 class="mb-4 text-sm font-semibold text-white">Selected Customer</h3>
                <div class="rounded-2xl border border-cyan-500/30 bg-cyan-950/30 p-6">
                    <div id="customerDetails" class="space-y-4"></div>
                    <div class="mt-6 flex justify-end">
                        <a id="continueBtn" href="#" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                            Continue to Loan Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let searchTimeout;
        const searchUrl = "{{ route('admin.loan-applications.search-customer-ajax', $loanProduct) }}";
        const loanDetailsUrl = "{{ route('admin.loan-applications.loan-details', [$loanProduct, ':customerId']) }}";
        let selectedCustomerId = null;

        document.getElementById('customerSearch').addEventListener('input', function(e) {
            const search = e.target.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (search.length < 2) {
                document.getElementById('searchResults').classList.add('hidden');
                document.getElementById('selectedCustomer').classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(searchUrl + '?search=' + encodeURIComponent(search), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        displaySearchResults(data.customers);
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                    });
            }, 300);
        });

        function displaySearchResults(customers) {
            const resultsDiv = document.getElementById('searchResults');
            const customersList = document.getElementById('customersList');
            
            if (customers.length === 0) {
                customersList.innerHTML = '<p class="text-slate-400 text-center py-4">No customers found.</p>';
                resultsDiv.classList.remove('hidden');
                return;
            }

            customersList.innerHTML = customers.map(customer => `
                <div class="rounded-xl border border-white/10 bg-white/5 p-4 hover:border-cyan-500/50 hover:bg-white/10 transition cursor-pointer" 
                     onclick="selectCustomer(${customer.id}, ${JSON.stringify(customer).replace(/"/g, '&quot;')})">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-white">${customer.name}</h4>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold
                                    ${customer.customer_type === 'company' ? 'bg-blue-500/20 text-blue-200' : (customer.customer_type === 'representative' ? 'bg-emerald-500/20 text-emerald-200' : 'bg-purple-500/20 text-purple-200')}">
                                    ${customer.customer_type === 'company' ? 'Company' : (customer.customer_type === 'representative' ? 'Representative' : 'Individual')}
                                </span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-sm text-slate-400">
                                <div><span class="text-slate-500">Phone:</span> ${customer.phone || 'N/A'}</div>
                                <div><span class="text-slate-500">NRC:</span> ${customer.national_id || 'N/A'}</div>
                                <div><span class="text-slate-500">Group:</span> ${customer.customer_group}</div>
                                <div><span class="text-slate-500">Max Loan:</span> ${formatCurrency(customer.maximum_loan_take)}</div>
                                <div class="col-span-2"><span class="text-slate-500">Company:</span> ${customer.company_name || 'N/A'}</div>
                                ${customer.customer_type === 'representative' ? `<div class="col-span-2"><span class="text-slate-500">Parent:</span> ${customer.parent_name || 'N/A'}</div>` : ''}
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            `).join('');
            
            resultsDiv.classList.remove('hidden');
        }

        function selectCustomer(customerId, customer) {
            selectedCustomerId = customer.borrower_id || customerId;
            
            document.getElementById('searchResults').classList.add('hidden');
            document.getElementById('selectedCustomer').classList.remove('hidden');
            
            const customerDetails = document.getElementById('customerDetails');
            customerDetails.innerHTML = `
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Customer Name</p>
                        <p class="text-sm font-medium text-white">${customer.name}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Phone Number</p>
                        <p class="text-sm font-medium text-white">${customer.phone || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">National ID</p>
                        <p class="text-sm font-medium text-white">${customer.national_id || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Email</p>
                        <p class="text-sm font-medium text-white">${customer.email || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Customer Group</p>
                        <p class="text-sm font-medium text-white">${customer.customer_group}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Maximum Loan Amount</p>
                        <p class="text-sm font-medium text-white">${formatCurrency(customer.maximum_loan_take)}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Available Loan Amount</p>
                        <p class="text-sm font-medium text-emerald-400">${formatCurrency(customer.available_loan_amount)}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Type</p>
                        <p class="text-sm font-medium text-white">${customer.customer_type}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Company</p>
                        <p class="text-sm font-medium text-white">${customer.company_name || 'N/A'}</p>
                    </div>
                    ${customer.customer_type === 'representative'
                        ? `<div><p class="text-xs uppercase tracking-wide text-cyan-400 mb-1">Parent Company Customer</p><p class="text-sm font-medium text-white">${customer.parent_name || 'N/A'}</p></div>`
                        : ''
                    }
                </div>
            `;
            
            const continueBtn = document.getElementById('continueBtn');
            continueBtn.href = loanDetailsUrl.replace(':customerId', selectedCustomerId);
        }

        function formatCurrency(amount) {
            return 'ZMW ' + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }
    </script>
    @endpush
@endsection

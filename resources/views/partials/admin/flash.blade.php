@if (session('status'))
    <div class="mb-6 alert {{ session('upload_failed') && session('upload_failed') > 0 ? 'alert-warning' : 'alert-success' }} flash-message-success" 
         x-data="{ show: true }" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-init="setTimeout(() => show = false, {{ session('upload_failed') && session('upload_failed') > 0 ? '10000' : '5000' }})">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
                @if(session('upload_failed') && session('upload_failed') > 0)
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                @else
                    <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium {{ session('upload_failed') && session('upload_failed') > 0 ? '' : '' }}">
                    {{ session('status') }}
                </p>
                @if(session('upload_batch_id') && session('upload_failed') && session('upload_failed') > 0)
                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('admin.customers.upload-batch.show', session('upload_batch_id')) }}" 
                           class="inline-flex items-center gap-2 rounded-xl border border-amber-500/50 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition shadow-md shadow-amber-500/20">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            View Failed Records ({{ session('upload_failed') }})
                        </a>
                    </div>
                @endif
            </div>
            <button @click="show = false" class="flex-shrink-0 text-inherit hover:opacity-80 transition" aria-label="Close">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
@endif

@if (session('error'))
    <div class="mb-6 alert alert-danger flash-message-error" 
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
            <div class="flex-shrink-0">
                <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="flex-1 text-sm font-medium">{{ session('error') }}</p>
            <button @click="show = false" class="flex-shrink-0 text-inherit hover:opacity-80 transition" aria-label="Close">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 alert alert-danger flash-message-error" 
         id="validation-errors"
         style="display: block !important;">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="alert-icon mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold mb-2">Please correct the following errors:</p>
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button onclick="document.getElementById('validation-errors').style.display='none'" class="flex-shrink-0 text-inherit hover:opacity-80 transition" aria-label="Close">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
@endif

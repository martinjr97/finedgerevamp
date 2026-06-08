@once
    @push('scripts')
        <script>
            (function initKycFilePreviews() {
                const bindForm = (form) => {
                    if (!form || form.dataset.kycPreviewInit === 'true') {
                        return;
                    }

                    form.dataset.kycPreviewInit = 'true';
                    const objectUrls = new Map();

                    const revokeUrl = (input) => {
                        const existing = objectUrls.get(input);
                        if (existing) {
                            URL.revokeObjectURL(existing);
                            objectUrls.delete(input);
                        }
                    };

                    const ensurePreview = (input) => {
                        let preview = input.nextElementSibling;
                        if (!preview || !preview.matches('[data-kyc-file-preview]')) {
                            preview = document.createElement('div');
                            preview.dataset.kycFilePreview = '';
                            preview.className = 'kyc-file-preview mt-2 hidden max-w-full';
                            preview.setAttribute('aria-live', 'polite');
                            input.insertAdjacentElement('afterend', preview);
                        }

                        return preview;
                    };

                    const renderPreview = (input, preview, file) => {
                        preview.classList.remove('hidden');
                        preview.innerHTML = '';

                        const wrap = document.createElement('div');
                        wrap.className =
                            'inline-flex max-w-full flex-col gap-1.5 rounded-xl border border-white/10 bg-black/25 p-2';

                        const frame = document.createElement('div');
                        frame.className =
                            'flex h-32 w-full max-w-[11rem] items-center justify-center overflow-hidden rounded-lg bg-white/5 sm:h-36 sm:max-w-[12rem]';

                        const isImage = file.type.startsWith('image/');

                        if (isImage) {
                            const img = document.createElement('img');
                            img.alt = 'Preview of ' + file.name;
                            img.className = 'max-h-full max-w-full object-contain';
                            img.src = URL.createObjectURL(file);
                            objectUrls.set(input, img.src);
                            frame.appendChild(img);
                        } else {
                            const icon = document.createElement('div');
                            icon.className = 'flex flex-col items-center gap-1 px-2 text-center';
                            icon.innerHTML =
                                '<svg class="h-8 w-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 4h10a2 2 0 012 2v14l-5-3-5 3V6a2 2 0 012-2z"/></svg>' +
                                '<span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">PDF / Document</span>';
                            frame.appendChild(icon);
                        }

                        const name = document.createElement('p');
                        name.className = 'max-w-[11rem] truncate text-xs text-slate-300 sm:max-w-[12rem]';
                        name.textContent = file.name;

                        const size = document.createElement('p');
                        size.className = 'text-[10px] text-slate-500';
                        const kb = file.size / 1024;
                        size.textContent = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(kb)) + ' KB';

                        wrap.appendChild(frame);
                        wrap.appendChild(name);
                        wrap.appendChild(size);
                        preview.appendChild(wrap);
                    };

                    const clearPreview = (input, preview) => {
                        revokeUrl(input);
                        preview.classList.add('hidden');
                        preview.innerHTML = '';
                    };

                    form.querySelectorAll('input[type="file"]').forEach((input) => {
                        const preview = ensurePreview(input);

                        input.addEventListener('change', () => {
                            revokeUrl(input);

                            const file = input.files && input.files[0];
                            if (!file) {
                                clearPreview(input, preview);
                                return;
                            }

                            renderPreview(input, preview, file);
                        });
                    });

                    form.addEventListener('reset', () => {
                        form.querySelectorAll('input[type="file"]').forEach((input) => {
                            const preview = input.nextElementSibling;
                            if (preview && preview.matches('[data-kyc-file-preview]')) {
                                clearPreview(input, preview);
                            }
                        });
                    });
                };

                const init = () => {
                    document.querySelectorAll('form[data-kyc-upload-form]').forEach(bindForm);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();
        </script>
    @endpush
@endonce

<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CollateralType;
use App\Models\CustomerRegistrationRequest;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\GeneralSetting;
use App\Models\LoanProduct;
use App\Models\District;
use App\Models\Ministry;
use App\Models\Province;
use App\Support\DocumentUploadRules;
use App\Support\NationalIdRules;
use App\Support\PublicRegistrationPaths;
use App\Support\ZambianPhoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationRequestController extends Controller
{
    public function choosePath(): View
    {
        $setting = $this->requireRegistrationEnabled();

        if (! PublicRegistrationPaths::hasAnyEnabledPath($setting)) {
            return view('customer.registration.unavailable');
        }

        $enabledPaths = PublicRegistrationPaths::enabledPathKeys($setting);

        return view('customer.registration.choose-path', [
            'enabledPaths' => $enabledPaths,
        ]);
    }

    public function createGovernmentWorker(): View
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::GOVERNMENT_WORKER);

        return view('customer.registration.government-worker', $this->governmentWorkerViewData());
    }

    public function createCollateralBased(): View
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::COLLATERAL_BASED);

        $product = PublicRegistrationPaths::resolveProduct($setting, PublicRegistrationPaths::COLLATERAL_BASED);

        return view('customer.registration.collateral-based', $this->collateralBasedViewData($product));
    }

    public function storeGovernmentWorker(Request $request): RedirectResponse
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::GOVERNMENT_WORKER);

        $product = PublicRegistrationPaths::resolveProduct($setting, PublicRegistrationPaths::GOVERNMENT_WORKER);
        if (! $product) {
            abort(404);
        }

        [$data, $employmentDetails] = $this->validateGovernmentWorkerRequest($request);

        return $this->persistRegistration(
            $request,
            PublicRegistrationPaths::GOVERNMENT_WORKER,
            $product->id,
            $data,
            employmentDetails: $employmentDetails,
        );
    }

    public function storeCollateralBased(Request $request): RedirectResponse
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::COLLATERAL_BASED);

        $product = PublicRegistrationPaths::resolveProduct($setting, PublicRegistrationPaths::COLLATERAL_BASED);
        if (! $product) {
            abort(404);
        }

        [$data, $collateralDetails] = $this->validateCollateralBasedRequest($request, $product);

        return $this->persistRegistration(
            $request,
            PublicRegistrationPaths::COLLATERAL_BASED,
            $product->id,
            $data,
            collateralDetails: $collateralDetails,
        );
    }

    public function retrieve(Request $request): RedirectResponse
    {
        $this->requireRegistrationEnabled();

        $data = $request->validate([
            'reference' => ['required', 'string'],
        ]);

        $registration = CustomerRegistrationRequest::query()
            ->where('reference', $data['reference'])
            ->first();

        if (! $registration) {
            return back()
                ->withInput()
                ->withErrors([
                    'reference' => 'We could not find an application with that Registration Request ID.',
                ]);
        }

        if (! in_array($registration->status, ['pending', 'reverted'], true)) {
            return back()
                ->withInput()
                ->withErrors([
                    'retrieve' => 'This application has already been processed and can no longer be updated.',
                ]);
        }

        return match ($registration->registration_path) {
            PublicRegistrationPaths::GOVERNMENT_WORKER => redirect()->route(
                'customer.register-request.government-worker.edit',
                $registration->reference
            ),
            PublicRegistrationPaths::COLLATERAL_BASED => redirect()->route(
                'customer.register-request.collateral-based.edit',
                $registration->reference
            ),
            default => back()
                ->withInput()
                ->withErrors(['reference' => 'This application cannot be edited online. Please contact support.']),
        };
    }

    public function editGovernmentWorker(Request $request, string $reference): View
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::GOVERNMENT_WORKER);

        $registration = $this->editableRegistration($reference, PublicRegistrationPaths::GOVERNMENT_WORKER);
        $this->flashOldInputFromRegistration($request, $registration);

        return view('customer.registration.government-worker', array_merge(
            $this->governmentWorkerViewData(),
            ['editingReference' => $registration->reference]
        ));
    }

    public function editCollateralBased(Request $request, string $reference): View
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::COLLATERAL_BASED);

        $registration = $this->editableRegistration($reference, PublicRegistrationPaths::COLLATERAL_BASED);
        $product = PublicRegistrationPaths::resolveProduct($setting, PublicRegistrationPaths::COLLATERAL_BASED);

        $this->flashOldInputFromRegistration($request, $registration);

        return view('customer.registration.collateral-based', array_merge(
            $this->collateralBasedViewData($product),
            ['editingReference' => $registration->reference]
        ));
    }

    public function updateGovernmentWorker(Request $request, string $reference): RedirectResponse
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::GOVERNMENT_WORKER);

        $registration = $this->editableRegistration($reference, PublicRegistrationPaths::GOVERNMENT_WORKER);
        $product = PublicRegistrationPaths::resolveProduct($setting, PublicRegistrationPaths::GOVERNMENT_WORKER);

        if (! $product) {
            abort(404);
        }

        [$data, $employmentDetails] = $this->validateGovernmentWorkerRequest($request);

        return $this->updateRegistration(
            $request,
            $registration,
            PublicRegistrationPaths::GOVERNMENT_WORKER,
            $product->id,
            $data,
            employmentDetails: $employmentDetails,
        );
    }

    public function updateCollateralBased(Request $request, string $reference): RedirectResponse
    {
        $setting = $this->requireRegistrationEnabled();
        $this->abortUnlessPathEnabled($setting, PublicRegistrationPaths::COLLATERAL_BASED);

        $registration = $this->editableRegistration($reference, PublicRegistrationPaths::COLLATERAL_BASED);
        $product = PublicRegistrationPaths::resolveProduct($setting, PublicRegistrationPaths::COLLATERAL_BASED);

        if (! $product) {
            abort(404);
        }

        [$data, $collateralDetails] = $this->validateCollateralBasedRequest($request, $product);

        return $this->updateRegistration(
            $request,
            $registration,
            PublicRegistrationPaths::COLLATERAL_BASED,
            $product->id,
            $data,
            collateralDetails: $collateralDetails,
        );
    }

    public function thankYou(): View
    {
        $this->requireRegistrationEnabled();

        return view('customer.registration.request-submitted');
    }

    private function requireRegistrationEnabled(): GeneralSetting
    {
        $setting = GeneralSetting::query()->first();

        if (! $setting || ! $setting->allow_customer_registration) {
            abort(404);
        }

        return $setting;
    }

    private function abortUnlessPathEnabled(GeneralSetting $setting, string $path): void
    {
        if (! PublicRegistrationPaths::isPathEnabled($setting, $path)) {
            abort(404);
        }
    }

    private function editableRegistration(string $reference, string $path): CustomerRegistrationRequest
    {
        $registration = CustomerRegistrationRequest::query()
            ->where('reference', $reference)
            ->where('registration_path', $path)
            ->firstOrFail();

        if (! in_array($registration->status, ['pending', 'reverted'], true)) {
            abort(403, 'This registration request can no longer be edited.');
        }

        return $registration;
    }

    /**
     * @return array<string, mixed>
     */
    private function commonRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ZambianPhoneRules::required(),
            'requested_loan_amount' => ['required', 'numeric', 'min:1'],
            'document_type' => ['nullable', 'in:passport,nrc,drivers_license,voters_card,other'],
            ...DocumentUploadRules::registrationFileRules(),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, array $rules): array
    {
        $rules = NationalIdRules::mergeRegistration($rules);

        $data = $request->validate(
            $rules,
            array_merge(ZambianPhoneRules::messages(), NationalIdRules::messages()),
            array_merge(ZambianPhoneRules::attributes(), NationalIdRules::attributes(), [
                'requested_loan_amount' => 'requested loan amount',
                'estimated_collateral_value' => 'estimated collateral value',
                'employee_number' => 'employee / payroll number',
                'date_of_employment' => 'date of employment',
                'bank_financial_institution_id' => 'bank',
                'bank_financial_institution_branch_id' => 'bank branch',
                'bank_account_name' => 'bank account name',
                'bank_account_number' => 'bank account number',
            ])
        );

        return $this->normalizeRegistrationSubmissionForStorage($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRegistrationSubmissionForStorage(array $data): array
    {
        foreach (['front_image', 'back_image', 'profile_picture', 'bank_statement', 'payslip', 'stand_picture'] as $key) {
            unset($data[$key]);
        }

        $excludeKeys = [
            // Stored as enum / internal codes, must remain lowercase.
            'document_type',
            'national_id_type',
            'email',
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }

            if (is_string($value)) {
                $data[$key] = Str::upper($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $employmentDetails
     * @param  array<string, mixed>|null  $collateralDetails
     */
    private function persistRegistration(
        Request $request,
        string $path,
        int $loanProductId,
        array $data,
        ?array $employmentDetails = null,
        ?array $collateralDetails = null,
    ): RedirectResponse {
        $kycPaths = [];
        $reference = $this->generateReference();

        try {
            $this->storeKycUploads($request, $kycPaths);
            $payload = array_merge($data, [
                'kyc_paths' => $kycPaths,
                'reference' => $reference,
            ]);

            CustomerRegistrationRequest::create([
                'reference' => $reference,
                'registration_path' => $path,
                'loan_product_id' => $loanProductId,
                'customer_group_id' => null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'national_id' => $data['national_id'] ?? null,
                'national_id_type' => $data['national_id_type'] ?? null,
                'tpin' => $data['tpin'] ?? null,
                'requested_loan_amount' => $data['requested_loan_amount'],
                'status' => 'pending',
                'payload' => $payload,
                'employment_details' => $employmentDetails,
                'collateral_details' => $collateralDetails,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            foreach ($kycPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to submit registration request: '.$e->getMessage()]);
        }

        return redirect()
            ->route('customer.register-request.thank-you')
            ->with('status', 'Your registration request has been received. We will contact you within 48 hours.')
            ->with('reference', $reference);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $employmentDetails
     * @param  array<string, mixed>|null  $collateralDetails
     */
    private function updateRegistration(
        Request $request,
        CustomerRegistrationRequest $registration,
        string $path,
        int $loanProductId,
        array $data,
        ?array $employmentDetails = null,
        ?array $collateralDetails = null,
    ): RedirectResponse {
        $existingPayload = $registration->payload ?? [];
        $kycPaths = $existingPayload['kyc_paths'] ?? [];
        $newPaths = [];

        try {
            $newPaths = $this->storeKycUploads($request, $kycPaths);
            $payload = array_merge($existingPayload, $data, [
                'kyc_paths' => $kycPaths,
                'reference' => $registration->reference,
            ]);

            $registration->update([
                'registration_path' => $path,
                'loan_product_id' => $loanProductId,
                'customer_group_id' => null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'national_id' => $data['national_id'] ?? null,
                'national_id_type' => $data['national_id_type'] ?? null,
                'tpin' => $data['tpin'] ?? null,
                'requested_loan_amount' => $data['requested_loan_amount'],
                'status' => 'pending',
                'payload' => $payload,
                'employment_details' => $employmentDetails,
                'collateral_details' => $collateralDetails,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            foreach ($newPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to update registration request: '.$e->getMessage()]);
        }

        return redirect()
            ->route('customer.register-request.thank-you')
            ->with('status', 'Your updated registration request has been submitted. We will contact you within 48 hours.')
            ->with('reference', $registration->reference);
    }

    /**
     * @param  array<string, string>  $existing
     * @return list<string> newly stored paths for cleanup on failure
     */
    private function storeKycUploads(Request $request, array &$existing = []): array
    {
        $newPaths = [];

        $map = [
            'front_image' => ['key' => 'front_image_path', 'dir' => 'registration-kyc/documents'],
            'back_image' => ['key' => 'back_image_path', 'dir' => 'registration-kyc/documents'],
            'profile_picture' => ['key' => 'profile_picture_path', 'dir' => 'registration-kyc/profile-pictures'],
            'bank_statement' => ['key' => 'bank_statement_path', 'dir' => 'registration-kyc/optional'],
            'payslip' => ['key' => 'payslip_path', 'dir' => 'registration-kyc/optional'],
        ];

        foreach ($map as $input => $config) {
            if ($request->hasFile($input)) {
                $path = $request->file($input)->store($config['dir'], 'public');
                $existing[$config['key']] = $path;
                $newPaths[] = $path;
            }
        }

        return $newPaths;
    }

    private function generateReference(): string
    {
        do {
            $reference = 'CRR-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (CustomerRegistrationRequest::where('reference', $reference)->exists());

        return $reference;
    }

    private function flashOldInputFromRegistration(Request $request, CustomerRegistrationRequest $registration): void
    {
        $employment = $registration->employment_details ?? [];
        $collateral = $registration->collateral_details ?? [];

        $ministryId = $employment['ministry_id'] ?? null;
        if (! empty($employment['ministry_is_other'])) {
            $ministryId = PublicRegistrationPaths::MINISTRY_OTHER;
        }

        $oldInput = array_merge(
            $registration->payload ?? [],
            $employment,
            $collateral,
            [
                'ministry_id' => $ministryId,
                'first_name' => $registration->first_name,
                'last_name' => $registration->last_name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'national_id' => $registration->national_id,
                'national_id_type' => $registration->national_id_type,
                'tpin' => $registration->tpin,
                'requested_loan_amount' => $registration->requested_loan_amount,
            ]
        );

        if (
            empty($oldInput['bank_financial_institution_id'] ?? null)
            && empty($oldInput['bank_financial_institution_branch_id'] ?? null)
            && filled($oldInput['bank_name'] ?? null)
        ) {
            $institution = FinancialInstitution::query()
                ->active()
                ->where('name', (string) $oldInput['bank_name'])
                ->first();

            if ($institution) {
                $oldInput['bank_financial_institution_id'] = $institution->id;

                if (filled($oldInput['bank_branch'] ?? null)) {
                    $branch = FinancialInstitutionBranch::query()
                        ->active()
                        ->where('financial_institution_id', $institution->id)
                        ->where('name', (string) $oldInput['bank_branch'])
                        ->first();

                    if ($branch) {
                        $oldInput['bank_financial_institution_branch_id'] = $branch->id;
                    }
                }
            }
        }

        $request->session()->flash('_old_input', $oldInput);
    }

    /**
     * @return array<string, mixed>
     */
    private function collateralBasedViewData(?LoanProduct $product): array
    {
        return [
            'collateralTypes' => CollateralType::query()
                ->where('loan_product_id', $product?->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'provinces' => Province::where('is_active', true)->orderBy('name')->get(),
            'districts' => District::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function validateCollateralBasedRequest(Request $request, LoanProduct $product): array
    {
        $rules = array_merge($this->commonRules(), [
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(
                    fn ($query) => $query->where('province_id', (int) $request->input('province_id'))
                ),
            ],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'collateral_type_id' => [
                'required',
                'integer',
                Rule::exists('collateral_types', 'id')
                    ->where('loan_product_id', $product->id)
                    ->where('is_active', true),
            ],
            'collateral_description' => ['required', 'string', 'max:2000'],
            'estimated_collateral_value' => ['required', 'numeric', 'min:1'],
        ]);

        $data = $this->validateRequest($request, $rules);

        $collateralType = CollateralType::query()
            ->where('id', $data['collateral_type_id'])
            ->where('loan_product_id', $product->id)
            ->firstOrFail();

        $province = Province::find((int) $data['province_id']);
        $district = District::find((int) $data['district_id']);

        $collateralDetails = [
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'],
            'province_id' => (int) $data['province_id'],
            'district_id' => (int) $data['district_id'],
            'province_name' => $province?->name,
            'district_name' => $district?->name,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'],
            'collateral_type_id' => $collateralType->id,
            'collateral_type_name' => $collateralType->name,
            'collateral_description' => $data['collateral_description'],
            'estimated_collateral_value' => $data['estimated_collateral_value'],
        ];

        return [$data, $collateralDetails];
    }

    /**
     * @return array<string, mixed>
     */
    private function governmentWorkerViewData(): array
    {
        return [
            'ministries' => Ministry::where('is_active', true)->orderBy('name')->get(),
            'provinces' => Province::where('is_active', true)->orderBy('name')->get(),
            'districts' => District::where('is_active', true)->orderBy('name')->get(),
            'ministryOtherValue' => PublicRegistrationPaths::MINISTRY_OTHER,
            'financialInstitutions' => FinancialInstitution::query()
                ->active()
                ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function validateGovernmentWorkerRequest(Request $request): array
    {
        $hasMinistries = Ministry::where('is_active', true)->exists();

        $selectedInstitutionId = $request->input('bank_financial_institution_id');
        $branchRule = Rule::exists('financial_institution_branches', 'id')
            ->whereNull('deleted_at')
            ->where('is_active', true);
        if (filled($selectedInstitutionId)) {
            $branchRule = $branchRule->where('financial_institution_id', (int) $selectedInstitutionId);
        }

        $rules = array_merge($this->commonRules(), [
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(
                    fn ($query) => $query->where('province_id', (int) $request->input('province_id'))
                ),
            ],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'employer_name' => [$hasMinistries ? 'nullable' : 'required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'employee_number' => ['required', 'string', 'max:50'],
            'date_of_employment' => ['required', 'date', 'before_or_equal:today'],
            'gross_salary' => ['nullable', 'numeric', 'min:0'],
            'net_salary' => ['required', 'numeric', 'min:0'],
            'bank_financial_institution_id' => [
                'required',
                'integer',
                Rule::exists('financial_institutions', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'bank_financial_institution_branch_id' => [
                'required',
                'integer',
                $branchRule,
            ],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'work_address_line1' => ['required', 'string', 'max:255'],
            'work_address_line2' => ['nullable', 'string', 'max:255'],
            'work_city' => ['nullable', 'string', 'max:100'],
            'work_province_id' => ['required', 'integer', 'exists:provinces,id'],
            'work_district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(
                    fn ($query) => $query->where('province_id', (int) $request->input('work_province_id'))
                ),
            ],
        ]);

        if ($hasMinistries) {
            $rules['ministry_id'] = ['required'];
        }

        $data = $this->validateRequest($request, $rules);

        if (! $hasMinistries) {
            $ministryId = null;
            $isOtherMinistry = true;
        } else {
            $ministryRaw = (string) $request->input('ministry_id');
            $isOtherMinistry = $ministryRaw === PublicRegistrationPaths::MINISTRY_OTHER;

            if ($isOtherMinistry) {
                if (empty(trim((string) ($data['employer_name'] ?? '')))) {
                    throw ValidationException::withMessages([
                        'employer_name' => 'Please enter your employer or ministry name.',
                    ]);
                }
                $ministryId = null;
            } else {
                if (! ctype_digit($ministryRaw) || ! Ministry::where('id', (int) $ministryRaw)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        'ministry_id' => 'Please select a valid ministry.',
                    ]);
                }
                $ministryId = (int) $ministryRaw;
                $data['employer_name'] = null;
            }
        }

        $homeProvince = Province::find((int) $data['province_id']);
        $homeDistrict = District::find((int) $data['district_id']);
        $workProvince = Province::find((int) $data['work_province_id']);
        $workDistrict = District::find((int) $data['work_district_id']);

        $financialInstitutionId = (int) $data['bank_financial_institution_id'];
        $financialInstitutionBranchId = (int) $data['bank_financial_institution_branch_id'];
        $financialInstitution = FinancialInstitution::query()->find($financialInstitutionId);
        $financialInstitutionBranch = FinancialInstitutionBranch::query()->find($financialInstitutionBranchId);

        if (
            ! $financialInstitution
            || ! $financialInstitutionBranch
            || (int) $financialInstitutionBranch->financial_institution_id !== $financialInstitutionId
        ) {
            throw ValidationException::withMessages([
                'bank_financial_institution_branch_id' => 'Please select a valid bank branch.',
            ]);
        }

        $employmentDetails = [
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'],
            'province_id' => (int) $data['province_id'],
            'district_id' => (int) $data['district_id'],
            'province_name' => $homeProvince?->name,
            'district_name' => $homeDistrict?->name,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'],
            'ministry_id' => $ministryId,
            'ministry_is_other' => $isOtherMinistry,
            'employer_name' => $isOtherMinistry ? trim((string) $data['employer_name']) : null,
            'department' => $data['department'] ?? null,
            'employee_number' => $data['employee_number'],
            'date_of_employment' => $data['date_of_employment'],
            'gross_salary' => $data['gross_salary'] ?? null,
            'net_salary' => $data['net_salary'],
            'bank_financial_institution_id' => $financialInstitutionId,
            'bank_financial_institution_branch_id' => $financialInstitutionBranchId,
            'bank_name' => Str::upper($financialInstitution->name),
            'bank_branch' => Str::upper($financialInstitutionBranch->name),
            'bank_account_name' => $data['bank_account_name'],
            'bank_account_number' => $data['bank_account_number'],
            'work_address_line1' => $data['work_address_line1'],
            'work_address_line2' => $data['work_address_line2'] ?? null,
            'work_city' => $data['work_city'] ?? null,
            'work_province_id' => (int) $data['work_province_id'],
            'work_district_id' => (int) $data['work_district_id'],
            'work_province_name' => $workProvince?->name,
            'work_district_name' => $workDistrict?->name,
        ];

        return [$data, $employmentDetails];
    }
}

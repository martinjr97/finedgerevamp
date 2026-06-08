<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerUploadBatch;
use App\Models\CustomerUploadRecord;
use App\Models\LoanProduct;
use App\Models\Province;
use App\Models\Ministry;
use App\Models\Company;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class CustomerBulkUploadController extends Controller
{
    /**
     * Download Excel template for a specific product type
     */
    public function downloadTemplate(Request $request, LoanProduct $product)
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);

        $templateData = $this->getTemplateData($product);
        $filename = 'customer_upload_template_' . strtolower($product->code) . '_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new class($templateData) implements FromCollection, WithHeadings, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                if (empty($this->data)) {
                    return collect([[]]);
                }
                
                // Return sample data row as collection
                return collect([array_values($this->data[0])]);
            }

            public function headings(): array
            {
                if (empty($this->data)) {
                    return [];
                }
                return array_keys($this->data[0]);
            }

            public function columnWidths(): array
            {
                $widths = [];
                $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
                foreach ($this->headings() as $index => $heading) {
                    $widths[$columns[$index]] = 20;
                }
                return $widths;
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']]],
                ];
            }
        }, $filename);
    }

    /**
     * Get template data based on product type
     */
    private function getTemplateData(LoanProduct $product): array
    {
        $baseFields = [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Phone' => '+260971234567',
            'National ID' => '123456/78/9',
            'TPIN' => '100000001',
            'Date of Birth' => '1990-01-15',
            'Gender' => 'male',
            'Address Line 1' => '123 Main Street',
            'Address Line 2' => 'Apt 4B',
            'City' => 'Lusaka',
            'Province' => 'Lusaka',
            'Postal Code' => '10101',
            'Country' => 'Zambia',
        ];

        switch ($product->category) {
            case 'government':
                return [[
                    ...$baseFields,
                    'Ministry' => 'Ministry of Health',
                    'Date of Employment' => '2020-01-01',
                    'Contract End Date' => '2025-12-31',
                    'Gross Salary' => '18000',
                    'Net Salary' => '14000',
                    'Deductions' => '4000',
                    'Work Address Line 1' => 'Government Building',
                    'Work City' => 'Lusaka',
                    'Work Province' => 'Lusaka',
                ]];

            case 'mou':
                return [[
                    ...$baseFields,
                    'Company' => 'BlueWave Telecoms',
                    'Position' => 'Manager',
                    'Unit' => 'Operations',
                    'Department' => 'IT',
                    'Date of Employment' => '2020-01-01',
                    'Contract End Date' => '2025-12-31',
                    'Gross Salary' => '20000',
                    'Net Salary' => '16000',
                    'Deductions' => '4000',
                ]];

            case 'character':
                return [[
                    ...$baseFields,
                    'Customer Group' => 'Character Builders',
                    'Is Employed' => 'Yes',
                    'Pay Day' => '25',
                    'Gross Salary' => '15000',
                    'Net Salary' => '12000',
                    'Next of Kin Name' => 'Jane Doe',
                    'Next of Kin Phone' => '+260972345678',
                    'Next of Kin Relationship' => 'Spouse',
                    'Next of Kin Address Line 1' => '123 Main Street',
                    'Next of Kin City' => 'Lusaka',
                    'Next of Kin Country' => 'Zambia',
                ]];

            case 'collateral':
                return [[
                    ...$baseFields,
                    'Customer Group' => 'Collateral Secure Group',
                    'Stand Number' => 'STND-001',
                    'Stand Description' => 'Commercial property',
                    'Monthly Income' => '15000',
                ]];

            case 'marketeer':
                return [[
                    ...$baseFields,
                    'Company' => 'Copperbelt Traders Cooperative',
                    'Market' => 'Kamwala Trading Market',
                    'Stand Number' => 'STND-001',
                    'Stand Description' => 'Fresh produce stand',
                    'Monthly Income' => '12000',
                    'Next of Kin Name' => 'Jane Doe',
                    'Next of Kin Phone' => '+260972345678',
                    'Next of Kin Relationship' => 'Spouse',
                    'Next of Kin Address Line 1' => '123 Main Street',
                    'Next of Kin City' => 'Lusaka',
                    'Next of Kin Country' => 'Zambia',
                ]];

            default:
                return [[$baseFields]];
        }
    }

    /**
     * Process bulk customer upload
     */
    public function upload(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);

        $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            'company_id' => 'nullable|exists:companies,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
        ]);

        $product = LoanProduct::findOrFail($request->loan_product_id);
        $file = $request->file('excel_file');
        
        // Store the file using Storage facade to get the correct path
        $filePath = $file->store('customer_uploads', 'local');
        $fileName = $file->getClientOriginalName();
        
        // Get the full path using Storage facade (handles disk root correctly)
        $fullPath = Storage::disk('local')->path($filePath);
        
        // Verify file was stored
        if (!file_exists($fullPath)) {
            \Log::error('File upload failed', [
                'file_path' => $filePath,
                'full_path' => $fullPath,
                'storage_root' => Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix(),
            ]);
            return redirect()
                ->route('admin.customers.create', ['product_id' => $product->id])
                ->with('error', 'Failed to store uploaded file. Please try again.');
        }

        // Create upload batch
        $batch = CustomerUploadBatch::create([
            'uploaded_by' => auth('admin')->id(),
            'loan_product_id' => $product->id,
            'company_id' => $request->company_id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'status' => 'processing',
        ]);

        // Process the Excel file immediately using the stored file path
        try {
            $this->processExcelFile($batch, $product, $request->company_id, $request->customer_group_id, $fullPath);
        } catch (\Exception $e) {
            $batch->update([
                'status' => 'failed',
                'notes' => 'Processing failed: ' . $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.customers.create', ['product_id' => $product->id])
                ->with('error', 'Failed to process upload: ' . $e->getMessage());
        }

        $batch->update(['status' => 'completed']);

        // Store upload batch info in session for flash message
        $message = "Upload completed. {$batch->successful_records} successful, {$batch->failed_records} failed.";
        
        return redirect()
            ->route('admin.customers.create', ['product_id' => $product->id])
            ->with('status', $message)
            ->with('upload_batch_id', $batch->id)
            ->with('upload_successful', $batch->successful_records)
            ->with('upload_failed', $batch->failed_records);
    }

    /**
     * Process Excel file and create customers
     */
    private function processExcelFile(CustomerUploadBatch $batch, LoanProduct $product, ?int $companyId, ?int $customerGroupId, ?string $filePath = null): void
    {
        // Use provided file path or get from Storage using the batch's stored path
        if (!$filePath) {
            $filePath = Storage::disk('local')->path($batch->file_path);
        }
        
        // Verify file exists
        if (!file_exists($filePath)) {
            throw new \Exception("Uploaded file not found. File path: {$filePath}. Please ensure the file was uploaded successfully.");
        }
        
        // Check if file is readable
        if (!is_readable($filePath)) {
            throw new \Exception("Uploaded file is not readable. Please check file permissions.");
        }
        
        try {
            $data = Excel::toArray([], $filePath);
        } catch (\Exception $e) {
            throw new \Exception("Failed to read Excel file: " . $e->getMessage());
        }
        if (empty($data) || empty($data[0])) {
            throw new \Exception('Excel file is empty or invalid.');
        }

        $rows = $data[0];
        $headers = array_map('strtolower', array_shift($rows)); // Remove header row
        $totalRecords = count($rows);

        $batch->update(['total_records' => $totalRecords]);

        $successful = 0;
        $failed = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because header is row 1, and array is 0-indexed
            $rowData = array_combine($headers, $row);

            try {
                $customer = $this->createCustomerFromRow($rowData, $product, $companyId, $customerGroupId, $batch);
                
                CustomerUploadRecord::create([
                    'batch_id' => $batch->id,
                    'row_number' => $rowNumber,
                    'data' => $rowData,
                    'status' => 'success',
                    'customer_id' => $customer->id,
                ]);

                $successful++;
            } catch (\Exception $e) {
                CustomerUploadRecord::create([
                    'batch_id' => $batch->id,
                    'row_number' => $rowNumber,
                    'data' => $rowData,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        $batch->update([
            'successful_records' => $successful,
            'failed_records' => $failed,
        ]);
    }

    /**
     * Create customer from Excel row data
     */
    private function createCustomerFromRow(array $row, LoanProduct $product, ?int $companyId, ?int $customerGroupId, CustomerUploadBatch $batch): Customer
    {
        // Map Excel columns to database fields with lookup logic
        $firstName = $this->getValue($row, ['first name', 'first_name']);
        $lastName = $this->getValue($row, ['last name', 'last_name']);
        $email = $this->getValue($row, ['email']);
        $phone = $this->getValue($row, ['phone']);
        $nationalId = $this->getValue($row, ['national id', 'national_id']);
        $nationalIdType = strtolower((string) ($this->getValue($row, ['national id type', 'national_id_type']) ?? ''));
        $tpin = $this->getValue($row, ['tpin']);
        $dateOfBirth = $this->getValue($row, ['date of birth', 'date_of_birth', 'dob']);
        $gender = $this->getValue($row, ['gender']);
        $addressLine1 = $this->getValue($row, ['address line 1', 'address_line1', 'address']);
        $addressLine2 = $this->getValue($row, ['address line 2', 'address_line2']);
        $city = $this->getValue($row, ['city']);
        $provinceName = $this->getValue($row, ['province']);
        $postalCode = $this->getValue($row, ['postal code', 'postal_code']);
        $country = $this->getValue($row, ['country']) ?: 'Zambia';

        // Validate required fields
        if (! $firstName || ! $lastName || ! $email || ! $nationalId) {
            throw new \Exception('Missing required fields: First Name, Last Name, Email, or National ID');
        }

        if ($nationalIdType === '') {
            $nationalIdType = \App\Rules\ZambianNrcNumber::isValid($nationalId)
                ? \App\Support\NationalIdRules::TYPE_NRC
                : \App\Support\NationalIdRules::TYPE_PASSPORT;
        }

        $allowedTypes = array_keys(\App\Support\NationalIdRules::typeLabels());
        if (! in_array($nationalIdType, $allowedTypes, true)) {
            throw new \Exception('Invalid national ID type. Use: '.implode(', ', $allowedTypes));
        }

        if ($nationalIdType === \App\Support\NationalIdRules::TYPE_NRC && ! \App\Rules\ZambianNrcNumber::isValid($nationalId)) {
            throw new \Exception('National ID must match Zambian NRC format, e.g. 111111/11/1');
        }

        // Check for duplicate email
        if (Customer::where('email', $email)->exists()) {
            throw new \Exception("Customer with email {$email} already exists");
        }

        // Lookup province
        $provinceId = null;
        if ($provinceName) {
            $province = Province::where('name', 'like', "%{$provinceName}%")
                ->orWhere('code', 'like', "%{$provinceName}%")
                ->first();
            if ($province) {
                $provinceId = $province->id;
            }
        }

        // Lookup gender (normalize)
        $genderValue = null;
        if ($gender) {
            $genderLower = strtolower($gender);
            if (in_array($genderLower, ['male', 'm', 'man'])) {
                $genderValue = 'male';
            } elseif (in_array($genderLower, ['female', 'f', 'woman'])) {
                $genderValue = 'female';
            } elseif (in_array($genderLower, ['other', 'o'])) {
                $genderValue = 'other';
            }
        }

        // Lookup customer group if not provided but needed
        $finalGroupId = $customerGroupId;
        if (!$finalGroupId && in_array($product->category, ['character', 'collateral'])) {
            $groupName = $this->getValue($row, ['customer group', 'customer_group', 'group']);
            if ($groupName) {
                $group = CustomerGroup::where('name', 'like', "%{$groupName}%")
                    ->where('loan_product_id', $product->id)
                    ->first();
                if ($group) {
                    $finalGroupId = $group->id;
                }
            }
        }

        // Auto-assign DEFAULT group for government products
        if ($product->category === 'government' && !$finalGroupId) {
            $defaultGroup = CustomerGroup::where('loan_product_id', $product->id)
                ->where('code', 'GOV-DEFAULT')
                ->first();
            if ($defaultGroup) {
                $finalGroupId = $defaultGroup->id;
            }
        }

        // Build customer data
        $customerData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone ? preg_replace('/\D/', '', $phone) : null,
            'national_id' => $nationalId,
            'national_id_type' => $nationalIdType,
            'tpin' => $tpin ?: null,
            'date_of_birth' => $dateOfBirth ? Carbon::parse($dateOfBirth)->format('Y-m-d') : null,
            'gender' => $genderValue,
            'address_line1' => $addressLine1,
            'address_line2' => $addressLine2,
            'city' => $city,
            'province_id' => $provinceId,
            'postal_code' => $postalCode,
            'country' => $country,
            'loan_product_id' => $product->id,
            'company_id' => $companyId,
            'customer_group_id' => $finalGroupId,
            'status' => 'pending', // Pending KYC
            'kyc_status' => 'unverified',
            'password' => Hash::make('1234'), // Default PIN
            'must_change_pin' => true,
            'verified_by' => $batch->uploaded_by,
        ];

        // Add product-specific fields
        $productData = $this->getProductSpecificData($row, $product);
        $customerData = array_merge($customerData, $productData);

        // Calculate maximum_loan_take if net_salary is provided
        if (isset($customerData['net_salary']) && $customerData['net_salary'] > 0) {
            $customerData['maximum_loan_take'] = $customerData['net_salary'] * 0.6; // 60% of net salary
        }

        $customer = Customer::create($customerData);

        // Handle marketeer customer details
        if ($product->category === 'marketeer') {
            $marketName = $this->getValue($row, ['market']);
            if ($marketName) {
                $market = Market::where('name', 'like', "%{$marketName}%")->first();
                if ($market) {
                    \App\Models\MarketeerCustomerDetail::create([
                        'customer_id' => $customer->id,
                        'market_id' => $market->id,
                        'stand_number' => $this->getValue($row, ['stand number', 'stand_number']) ?: 'STND-' . $customer->id,
                        'stand_description' => $this->getValue($row, ['stand description', 'stand_description']) ?: 'Market stand',
                        'monthly_income' => $customerData['net_salary'] ?? 0,
                    ]);
                }
            }
        }

        return $customer;
    }

    /**
     * Get product-specific data from row
     */
    private function getProductSpecificData(array $row, LoanProduct $product): array
    {
        $data = [];

        switch ($product->category) {
            case 'government':
                $ministryName = $this->getValue($row, ['ministry']);
                if ($ministryName) {
                    $ministry = Ministry::where('name', 'like', "%{$ministryName}%")->first();
                    if ($ministry) {
                        $data['ministry_id'] = $ministry->id;
                    }
                }
                $dateOfEmployment = $this->getValue($row, ['date of employment', 'date_of_employment']);
                if ($dateOfEmployment) {
                    try {
                        $data['date_of_employment'] = Carbon::parse($dateOfEmployment)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date, skip
                    }
                }
                $contractEndDate = $this->getValue($row, ['contract end date', 'contract_end_date']);
                if ($contractEndDate) {
                    try {
                        $data['contract_end_date'] = Carbon::parse($contractEndDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date, skip
                    }
                }
                $data['gross_salary'] = $this->getValue($row, ['gross salary', 'gross_salary']) ? (float)$this->getValue($row, ['gross salary', 'gross_salary']) : null;
                $data['net_salary'] = $this->getValue($row, ['net salary', 'net_salary']) ? (float)$this->getValue($row, ['net salary', 'net_salary']) : null;
                $data['deductions'] = $this->getValue($row, ['deductions']) ? (float)$this->getValue($row, ['deductions']) : 0;
                $data['employee_number'] = $this->getValue($row, ['employee number', 'employee_number']);
                $data['employment_status'] = 'employed';
                
                // Work address
                $data['work_address_line1'] = $this->getValue($row, ['work address line 1', 'work_address_line1']);
                $data['work_city'] = $this->getValue($row, ['work city', 'work_city']);
                $workProvinceName = $this->getValue($row, ['work province', 'work_province']);
                if ($workProvinceName) {
                    $workProvince = Province::where('name', 'like', "%{$workProvinceName}%")->first();
                    if ($workProvince) {
                        $data['work_province_id'] = $workProvince->id;
                    }
                }
                break;

            case 'mou':
                $data['position'] = $this->getValue($row, ['position']);
                $data['unit'] = $this->getValue($row, ['unit']);
                $data['department'] = $this->getValue($row, ['department']);
                $dateOfEmployment = $this->getValue($row, ['date of employment', 'date_of_employment']);
                if ($dateOfEmployment) {
                    try {
                        $data['date_of_employment'] = Carbon::parse($dateOfEmployment)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date, skip
                    }
                }
                $contractEndDate = $this->getValue($row, ['contract end date', 'contract_end_date']);
                if ($contractEndDate) {
                    try {
                        $data['contract_end_date'] = Carbon::parse($contractEndDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date, skip
                    }
                }
                $data['gross_salary'] = $this->getValue($row, ['gross salary', 'gross_salary']) ? (float)$this->getValue($row, ['gross salary', 'gross_salary']) : null;
                $data['net_salary'] = $this->getValue($row, ['net salary', 'net_salary']) ? (float)$this->getValue($row, ['net salary', 'net_salary']) : null;
                $data['employee_number'] = $this->getValue($row, ['employee number', 'employee_number']);
                $data['employment_status'] = 'employed';
                break;

            case 'character':
                $isEmployed = $this->getValue($row, ['is employed', 'is_employed', 'employed']);
                $data['is_employed'] = in_array(strtolower($isEmployed ?? ''), ['yes', 'y', 'true', '1', 'employed']) ? true : false;
                if ($data['is_employed']) {
                    $data['employee_number'] = $this->getValue($row, ['employee number', 'employee_number']);
                }
                $payDay = $this->getValue($row, ['pay day', 'pay_day', 'payday']);
                if ($payDay && is_numeric($payDay)) {
                    $data['payday'] = (int)$payDay;
                }
                $data['gross_salary'] = $this->getValue($row, ['gross salary', 'gross_salary']) ? (float)$this->getValue($row, ['gross salary', 'gross_salary']) : null;
                $data['net_salary'] = $this->getValue($row, ['net salary', 'net_salary']) ? (float)$this->getValue($row, ['net salary', 'net_salary']) : null;
                
                // Next of kin
                $data['next_of_kin_name'] = $this->getValue($row, ['next of kin name', 'next_of_kin_name']);
                $data['next_of_kin_phone'] = $this->getValue($row, ['next of kin phone', 'next_of_kin_phone']);
                $data['next_of_kin_relationship'] = $this->getValue($row, ['next of kin relationship', 'next_of_kin_relationship']);
                $data['next_of_kin_address_line1'] = $this->getValue($row, ['next of kin address line 1', 'next_of_kin_address_line1']);
                $data['next_of_kin_city'] = $this->getValue($row, ['next of kin city', 'next_of_kin_city']);
                $data['next_of_kin_country'] = $this->getValue($row, ['next of kin country', 'next_of_kin_country']) ?: 'Zambia';
                break;

            case 'collateral':
                $data['stand_number'] = $this->getValue($row, ['stand number', 'stand_number']);
                $data['stand_description'] = $this->getValue($row, ['stand description', 'stand_description']);
                $data['net_salary'] = $this->getValue($row, ['monthly income', 'monthly_income']) ? (float)$this->getValue($row, ['monthly income', 'monthly_income']) : null;
                $data['employment_status'] = 'self_employed';
                break;

            case 'marketeer':
                // Market will be handled in createCustomerFromRow after customer creation
                $data['net_salary'] = $this->getValue($row, ['monthly income', 'monthly_income']) ? (float)$this->getValue($row, ['monthly income', 'monthly_income']) : null;
                $data['employment_status'] = 'self_employed';
                
                // Next of kin
                $data['next_of_kin_name'] = $this->getValue($row, ['next of kin name', 'next_of_kin_name']);
                $data['next_of_kin_phone'] = $this->getValue($row, ['next of kin phone', 'next_of_kin_phone']);
                $data['next_of_kin_relationship'] = $this->getValue($row, ['next of kin relationship', 'next_of_kin_relationship']);
                $data['next_of_kin_address_line1'] = $this->getValue($row, ['next of kin address line 1', 'next_of_kin_address_line1']);
                $data['next_of_kin_city'] = $this->getValue($row, ['next of kin city', 'next_of_kin_city']);
                $data['next_of_kin_country'] = $this->getValue($row, ['next of kin country', 'next_of_kin_country']) ?: 'Zambia';
                break;
        }

        return $data;
    }

    /**
     * Get value from row by trying multiple key variations
     */
    private function getValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key])) {
                $value = $row[$key];
                return $value !== null && $value !== '' ? (string)$value : null;
            }
        }
        return null;
    }

    /**
     * Show upload batch details
     */
    public function showBatch(CustomerUploadBatch $batch): View
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);

        $failedRecords = $batch->failedRecords()->with('discardedBy')->paginate(50);
        
        return view('admin.customers.upload-batch.show', [
            'batch' => $batch,
            'failedRecords' => $failedRecords,
        ]);
    }

    /**
     * Discard a failed record
     */
    public function discardRecord(CustomerUploadRecord $record): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);

        if ($record->status !== 'failed') {
            return redirect()->back()->with('error', 'Only failed records can be discarded.');
        }

        if ($record->isDiscarded()) {
            return redirect()->back()->with('error', 'This record has already been discarded.');
        }

        $record->update([
            'discarded_at' => now(),
            'discarded_by' => auth('admin')->id(),
        ]);

        return redirect()->back()->with('status', 'Record discarded successfully. It will no longer be available for editing or retry, but the error message is preserved for audit purposes.');
    }

    /**
     * Show edit form for failed record
     */
    public function editRecord(CustomerUploadRecord $record): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);

        if ($record->status !== 'failed') {
            return redirect()->back()->with('error', 'This record is not in failed status.');
        }

        if ($record->isDiscarded()) {
            return redirect()->back()->with('error', 'This record has been discarded and cannot be edited.');
        }

        $batch = $record->batch;
        $product = $batch->loanProduct;
        
        // Get related data for dropdowns
        $provinces = Province::where('is_active', true)->orderBy('name')->get();
        $ministries = Ministry::where('is_active', true)->orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        $customerGroups = collect();
        if (in_array($product->category, ['character', 'collateral'])) {
            $customerGroups = CustomerGroup::where('loan_product_id', $product->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }
        $markets = collect();
        if ($product->category === 'marketeer') {
            $markets = Market::where('is_active', true)->orderBy('name')->get();
        }

        return view('admin.customers.upload-batch.edit', [
            'record' => $record,
            'batch' => $batch,
            'product' => $product,
            'provinces' => $provinces,
            'ministries' => $ministries,
            'companies' => $companies,
            'customerGroups' => $customerGroups,
            'markets' => $markets,
        ]);
    }

    /**
     * Update failed record data
     */
    public function updateRecord(Request $request, CustomerUploadRecord $record): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);

        if ($record->status !== 'failed') {
            return redirect()->back()->with('error', 'This record is not in failed status.');
        }

        if ($record->isDiscarded()) {
            return redirect()->back()->with('error', 'This record has been discarded and cannot be updated.');
        }

        // Get the current data and update it with form values
        $data = $record->data;
        
        // Update all fields from request
        foreach ($request->except(['_token', '_method']) as $key => $value) {
            // Normalize key to match Excel column format (with spaces)
            $normalizedKey = str_replace('_', ' ', strtolower($key));
            $data[$normalizedKey] = $value;
            
            // Also keep underscore version for compatibility
            $data[$key] = $value;
        }

        $record->update(['data' => $data]);

        return redirect()
            ->route('admin.customers.upload-batch.show', $record->batch)
            ->with('status', 'Record data updated successfully. You can now retry the upload.');
    }

    /**
     * Retry failed record
     */
    public function retryRecord(CustomerUploadRecord $record): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.create'), 403);

        if ($record->status !== 'failed') {
            return redirect()->back()->with('error', 'This record is not in failed status.');
        }

        if ($record->isDiscarded()) {
            return redirect()->back()->with('error', 'This record has been discarded and cannot be retried.');
        }

        try {
            $batch = $record->batch;
            $product = $batch->loanProduct;
            
            // Try to get customer group from row data if not set in batch
            $customerGroupId = null;
            if (in_array($product->category, ['character', 'collateral'])) {
                $groupName = $this->getValue($record->data, ['customer group', 'customer_group', 'group']);
                if ($groupName) {
                    $group = CustomerGroup::where('name', 'like', "%{$groupName}%")
                        ->where('loan_product_id', $product->id)
                        ->first();
                    if ($group) {
                        $customerGroupId = $group->id;
                    }
                }
            }
            
            $customer = $this->createCustomerFromRow(
                $record->data,
                $product,
                $batch->company_id,
                $customerGroupId,
                $batch
            );

            $record->update([
                'status' => 'success',
                'customer_id' => $customer->id,
                'error_message' => null,
            ]);

            $batch->increment('successful_records');
            $batch->decrement('failed_records');

            return redirect()->back()->with('status', 'Customer created successfully.');
        } catch (\Exception $e) {
            $record->update([
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to create customer: ' . $e->getMessage());
        }
    }
}

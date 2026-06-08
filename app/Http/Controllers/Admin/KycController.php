<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\DocumentUploadRules;
use App\Models\KycDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class KycController extends Controller
{
    public function create(Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('kyc.create'), 403);

        $customer->load('loanProduct');
        $latestKyc = $customer->latestKycDocument;

        return view('admin.customers.kyc.create', compact('customer', 'latestKyc'));
    }

    public function show(Customer $customer): View
    {
        $kycDocument = $customer->latestKycDocument;
        abort_unless($kycDocument, 404, 'KYC documents not found for this customer.');
        
        $kycDocument->load('verifier');
        return view('admin.customers.kyc.show', compact('customer', 'kycDocument'));
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('kyc.create'), 403);

        $customer->loadMissing('loanProduct');

        $isMarketeer = $customer->loanProduct && $customer->loanProduct->category === 'marketeer';
        $isGovernment = $customer->loanProduct && $customer->loanProduct->category === 'government';
        $isSmeCompany = $customer->loanProduct
            && $customer->loanProduct->category === 'sme'
            && $customer->customer_type === 'company';

        $employmentDocumentRule = DocumentUploadRules::requiredPdfOrImage();

        $rules = [
            'document_type' => 'required|in:passport,nrc,drivers_license,voters_card,other,company_documents',
            'front_image' => DocumentUploadRules::requiredImage(),
            'back_image' => DocumentUploadRules::nullableImage(),
            'profile_picture' => DocumentUploadRules::nullableImage(),
            'bank_statement' => DocumentUploadRules::nullablePdfOrImage(),
            'payslip' => DocumentUploadRules::nullablePdfOrImage(),
        ];

        if ($isSmeCompany) {
            $rules = [
                'document_type' => 'required|in:company_documents',
                'front_image' => DocumentUploadRules::requiredPdfOrImage(),
                'back_image' => DocumentUploadRules::nullablePdfOrImage(),
                'profile_picture' => DocumentUploadRules::nullablePdfOrImage(),
                'bank_statement' => DocumentUploadRules::requiredPdfOrImage(),
                'payslip' => DocumentUploadRules::requiredPdfOrImage(),
            ];
        }

        // Stand picture is required for marketeer customers
        if ($isMarketeer) {
            $rules['stand_picture'] = DocumentUploadRules::requiredImage();
        }

        if ($isGovernment) {
            $rules['bank_statement'] = $employmentDocumentRule;
            $rules['payslip'] = $employmentDocumentRule;
        }

        $validated = $request->validate($rules);

        try {
            // Store uploaded files
            $frontImagePath = $request->file('front_image')->store('kyc/documents', 'public');
            $backImagePath = $request->hasFile('back_image') 
                ? $request->file('back_image')->store('kyc/documents', 'public') 
                : null;
            $profilePicturePath = $request->hasFile('profile_picture') 
                ? $request->file('profile_picture')->store('kyc/profile-pictures', 'public') 
                : null;
            $bankStatementPath = $request->hasFile('bank_statement') 
                ? $request->file('bank_statement')->store('kyc/optional', 'public') 
                : null;
            $payslipPath = $request->hasFile('payslip') 
                ? $request->file('payslip')->store('kyc/optional', 'public') 
                : null;
            $standPicturePath = ($isMarketeer && $request->hasFile('stand_picture')) 
                ? $request->file('stand_picture')->store('kyc/stand-pictures', 'public') 
                : null;

            // Create KYC document record
            $kycDocument = KycDocument::create([
                'customer_id' => $customer->id,
                'document_type' => $validated['document_type'],
                'front_image_path' => $frontImagePath,
                'back_image_path' => $backImagePath,
                'profile_picture_path' => $profilePicturePath,
                'stand_picture_path' => $standPicturePath,
                'bank_statement_path' => $bankStatementPath,
                'payslip_path' => $payslipPath,
                'status' => 'pending',
            ]);

            // Check if customer is approved and if approval is required
            $requiresApproval = config('approval.customers.create', false);
            $isApproved = $customer->approval_status === 'approved';

            if (!$requiresApproval || $isApproved) {
                // Auto-verify KYC and activate customer (only if approved or approval not required)
                $kycDocument->update([
                    'status' => 'verified',
                    'verified_by' => auth('admin')->id(),
                    'verified_at' => now(),
                ]);

                $customer->update([
                    'status' => 'active',
                    'kyc_status' => 'verified',
                ]);

                // TODO: Send notification email to customer with temporary password and login URL
                // TODO: Force password change on first login

                return redirect()
                    ->route('admin.customers.show', $customer)
                    ->with('status', 'KYC documents uploaded and verified. Customer account is now active.');
            } else {
                // Keep as pending for approval
                return redirect()
                    ->route('admin.customers.show', $customer)
                    ->with('status', 'KYC documents uploaded successfully. Customer is pending approval before activation.');
            }
        } catch (\Exception $e) {
            // Clean up uploaded files on error
            if (isset($frontImagePath)) {
                Storage::disk('public')->delete($frontImagePath);
            }
            if (isset($backImagePath)) {
                Storage::disk('public')->delete($backImagePath);
            }
            if (isset($profilePicturePath)) {
                Storage::disk('public')->delete($profilePicturePath);
            }
            if (isset($bankStatementPath)) {
                Storage::disk('public')->delete($bankStatementPath);
            }
            if (isset($payslipPath)) {
                Storage::disk('public')->delete($payslipPath);
            }
            if (isset($standPicturePath)) {
                Storage::disk('public')->delete($standPicturePath);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to upload KYC documents: '.$e->getMessage());
        }
    }
}

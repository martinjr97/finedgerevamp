<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentDetail extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'method_type',
        'bank_financial_institution_id',
        'bank_financial_institution_branch_id',
        'bank_name',
        'bank_branch',
        'account_name',
        'account_number',
        'wallet_provider_id',
        'wallet_provider',
        'wallet_number',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankFinancialInstitution(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitution::class, 'bank_financial_institution_id');
    }

    public function bankFinancialInstitutionBranch(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitutionBranch::class, 'bank_financial_institution_branch_id');
    }

    public function walletProvider(): BelongsTo
    {
        return $this->belongsTo(WalletProvider::class);
    }
}

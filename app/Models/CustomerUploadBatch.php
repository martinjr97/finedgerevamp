<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerUploadBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'loan_product_id',
        'company_id',
        'file_name',
        'file_path',
        'total_records',
        'successful_records',
        'failed_records',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'successful_records' => 'integer',
        'failed_records' => 'integer',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(CustomerUploadRecord::class, 'batch_id');
    }

    public function failedRecords(): HasMany
    {
        return $this->hasMany(CustomerUploadRecord::class, 'batch_id')
            ->where('status', 'failed');
    }

    public function pendingRecords(): HasMany
    {
        return $this->hasMany(CustomerUploadRecord::class, 'batch_id')
            ->where('status', 'pending');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupLoanApplicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_loan_application_id',
        'document_name',
        'file_path',
        'description',
        'uploaded_by',
    ];

    public function groupLoanApplication(): BelongsTo
    {
        return $this->belongsTo(GroupLoanApplication::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LoanPaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'period_number',
        'due_date',
        'expected_amount',
        'principal_component',
        'interest_component',
        'fee_component',
        'schedule_basis',
        'is_projected_interest',
        'amount_paid',
        'remaining_amount',
        'status',
        'paid_at',
        'days_overdue',
        'is_restructured',
        'restructured_at',
        'loan_extension_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'expected_amount' => 'decimal:2',
            'principal_component' => 'decimal:2',
            'interest_component' => 'decimal:2',
            'fee_component' => 'decimal:2',
            'is_projected_interest' => 'boolean',
            'amount_paid' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'paid_at' => 'date',
            'days_overdue' => 'integer',
            'is_restructured' => 'boolean',
            'restructured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Exclude restructured schedule rows by default from repayment/reminder flows.
        static::addGlobalScope('non_restructured', function (Builder $query): void {
            $query->where('is_restructured', false);
        });
    }

    /**
     * Relationship to Loan
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function loanExtension(): BelongsTo
    {
        return $this->belongsTo(LoanExtension::class);
    }

    /**
     * Update status based on current date and payment status
     */
    public function updateStatus(): void
    {
        $today = Carbon::today();
        
        // If fully paid, mark as paid
        if ($this->remaining_amount <= 0 || $this->amount_paid >= $this->expected_amount) {
            $this->status = 'paid';
            if (!$this->paid_at && $this->amount_paid >= $this->expected_amount) {
                $this->paid_at = $today;
            }
            $this->days_overdue = 0;
            $this->save();
            return;
        }
        
        // Check if due date has passed
        if ($today->greaterThan($this->due_date)) {
            if ($this->amount_paid > 0) {
                $this->status = 'partial';
            } else {
                $this->status = 'overdue';
            }
            // Calculate days overdue correctly (today - due_date)
            $this->days_overdue = max(0, $this->due_date->diffInDays($today, false));
        } elseif ($today->equalTo($this->due_date)) {
            // Due today
            if ($this->amount_paid >= $this->expected_amount) {
                $this->status = 'paid';
                if (!$this->paid_at) {
                    $this->paid_at = $today;
                }
                $this->days_overdue = 0;
            } elseif ($this->amount_paid > 0) {
                $this->status = 'partial';
                $this->days_overdue = 0;
            } else {
                $this->status = 'upcoming';
                $this->days_overdue = 0;
            }
        } else {
            // Future date
            if ($this->amount_paid >= $this->expected_amount) {
                $this->status = 'paid_early';
                if (!$this->paid_at) {
                    $this->paid_at = $today;
                }
                $this->days_overdue = 0;
            } elseif ($this->amount_paid > 0) {
                $this->status = 'partial';
                $this->days_overdue = 0;
            } else {
                $this->status = 'upcoming';
                $this->days_overdue = 0;
            }
        }
        
        $this->save();
    }

    /**
     * Check if this schedule item is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || 
               ($this->due_date->isPast() && $this->remaining_amount > 0);
    }

    /**
     * Check if this schedule item needs a reminder
     */
    public function needsReminder(int $daysBefore = 3): bool
    {
        if ($this->is_restructured) {
            return false;
        }

        if ($this->status === 'paid' || $this->status === 'paid_early') {
            return false;
        }
        
        $today = Carbon::today();
        $daysUntilDue = $today->diffInDays($this->due_date, false);
        
        // Remind if due date is within X days
        return $daysUntilDue >= 0 && $daysUntilDue <= $daysBefore;
    }
}

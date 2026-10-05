<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProcurementRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'requester_id',
        'department',
        'vendor_id',
        'vendor_name',
        'item_description',
        'quantity',
        'amount',
        'justification',
        'required_by',
        'status',
        'current_approver_id',
        'submitted_at',
        'supporting_documents',
    ];

    protected $casts = [
        'amount' => 'float',
        'quantity' => 'integer',
        'supporting_documents' => 'array',
        'required_by' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(Clarification::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function budgetSnapshot(): ?Budget
    {
        return Budget::where('department', $this->department)->first();
    }

    public function budgetRemaining(): float
    {
        $budget = $this->budgetSnapshot();

        if (! $budget) {
            return 0;
        }

        return (float) $budget->remainingAmount() - (float) $this->amount;
    }

    public function getBudgetStatusAttribute(): string
    {
        $budget = $this->budgetSnapshot();

        if (! $budget) {
            return 'No budget configured';
        }

        return $this->budgetRemaining() >= 0 ? 'Within Budget' : 'Exceeds Budget';
    }
}

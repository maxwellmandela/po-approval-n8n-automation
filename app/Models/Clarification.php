<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clarification extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_request_id',
        'requested_by',
        'question',
        'response',
        'responded_at',
        'status',
        'response_documents',
    ];

    protected $casts = [
        'response_documents' => 'array',
        'responded_at' => 'datetime',
    ];

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationEmailReply extends Model
{
    protected $fillable = [
        'provider_message_id',
        'procurement_request_id',
        'sender_email',
        'action',
        'comment',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }
}
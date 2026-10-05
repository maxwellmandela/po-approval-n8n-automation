<?php

namespace App\Services;

use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IntegrationEventService
{
    public function dispatch(string $event, ProcurementRequest $request, ?User $actor = null, array $extra = []): array
    {
        $payload = [
            'event' => $event,
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'amount' => $request->amount,
            'department' => $request->department,
            'status' => $request->status,
            'requester' => [
                'name' => $request->requester?->name,
                'email' => $request->requester?->email,
            ],
            'approver' => [
                'name' => $request->current_approver?->name,
                'email' => $request->current_approver?->email,
            ],
            'actor' => $actor ? [
                'name' => $actor->name,
                'email' => $actor->email,
                'role' => $actor->role,
            ] : null,
        ];

        $payload = array_merge($payload, $extra);

        $webhookUrl = config('procurement.n8n_base_url');
        $secret = config('procurement.n8n_webhook_secret');

        if ($webhookUrl && $secret) {
            $signature = hash_hmac('sha256', json_encode($payload), $secret);

            Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-N8N-Signature' => $signature,
                'X-Procurement-Event' => $event,
            ])->post($webhookUrl, $payload);
        }

        return $payload;
    }
}

<?php

namespace Tests\Feature;

use App\Models\ProcurementRequest;
use App\Models\User;
use App\Services\IntegrationEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationEventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_n8n_event_includes_shared_secret_and_request_details(): void
    {
        Http::fake();
        config([
            'procurement.n8n_base_url' => 'https://n8n.example.test/webhook/procurement-events',
            'procurement.n8n_webhook_secret' => 'local-test-secret',
        ]);

        $requester = User::factory()->create(['role' => 'requester']);
        $approver = User::factory()->create(['role' => 'approver']);
        $procurementRequest = ProcurementRequest::create([
            'request_number' => 'PR-2026-0100',
            'requester_id' => $requester->id,
            'department' => 'IT',
            'item_description' => 'Developer laptop',
            'quantity' => 1,
            'amount' => 125000,
            'justification' => 'Equipment for a new developer.',
            'status' => 'submitted',
            'current_approver_id' => $approver->id,
        ]);

        app(IntegrationEventService::class)->dispatch('procurement.request.submitted', $procurementRequest);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://n8n.example.test/webhook/procurement-events'
            && $request->hasHeader('X-N8N-Webhook-Secret', 'local-test-secret')
            && $request->hasHeader('X-N8N-Signature')
            && $request->data()['request_number'] === 'PR-2026-0100'
            && $request->data()['item_description'] === 'Developer laptop'
            && $request->data()['approver']['email'] === $approver->email
        );
    }
}
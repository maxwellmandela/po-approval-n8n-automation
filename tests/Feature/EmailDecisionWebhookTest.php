<?php

namespace Tests\Feature;

use App\Models\ProcurementRequest;
use App\Models\User;
use App\Models\Clarification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDecisionWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_approver_can_request_clarification_by_email(): void
    {
        [$request, $approver] = $this->makeSubmittedRequest();

        $response = $this->postDecision([
            'message_id' => 'gmail-message-clarify-1',
            'request_number' => $request->request_number,
            'sender_email' => $approver->email,
            'sender_authenticated' => true,
            'action' => 'clarify',
            'comment' => 'Please explain why this laptop model is required.',
        ]);

        $response->assertOk()->assertJsonPath('request_status', 'clarification_required');
        $this->assertDatabaseHas('clarifications', [
            'procurement_request_id' => $request->id,
            'requested_by' => $approver->id,
            'question' => 'Please explain why this laptop model is required.',
        ]);
    }

    public function test_assigned_approver_can_approve_email_reply_idempotently(): void
    {
        [$request, $approver] = $this->makeSubmittedRequest();
        $payload = [
            'message_id' => 'gmail-message-approve-1',
            'request_number' => $request->request_number,
            'sender_email' => $approver->email,
            'sender_authenticated' => true,
            'action' => 'approve',
            'comment' => 'Approved.',
        ];

        $this->postDecision($payload)->assertOk()->assertJsonPath('request_status', 'approved');
        $this->postDecision($payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('approvals', 1);
        $this->assertDatabaseCount('integration_email_replies', 1);
    }

    public function test_unassigned_sender_cannot_decide_a_request(): void
    {
        [$request] = $this->makeSubmittedRequest();

        $this->postDecision([
            'message_id' => 'gmail-message-wrong-sender-1',
            'request_number' => $request->request_number,
            'sender_email' => 'someone-else@example.com',
            'sender_authenticated' => true,
            'action' => 'approve',
            'comment' => 'Approved.',
        ])->assertForbidden();

        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertDatabaseCount('approvals', 0);
    }

    public function test_email_decision_requires_the_shared_secret(): void
    {
        config(['procurement.n8n_webhook_secret' => null]);
        [$request, $approver] = $this->makeSubmittedRequest();

        $this->postDecision([
            'message_id' => 'gmail-message-no-secret-1',
            'request_number' => $request->request_number,
            'sender_email' => $approver->email,
            'sender_authenticated' => true,
            'action' => 'approve',
        ], false)->assertServiceUnavailable();
    }

    public function test_requester_can_respond_to_a_pending_clarification_by_email(): void
    {
        [$request, $requester] = $this->makeClarificationRequest();
        $reply = 'The Probook meets our approved development image requirements.';

        $response = $this->postClarificationResponse([
            'message_id' => 'gmail-requester-reply-1',
            'request_number' => $request->request_number,
            'sender_email' => $requester->email,
            'sender_authenticated' => true,
            'body' => $reply,
        ]);

        $response->assertOk()->assertJsonPath('request_status', 'resubmitted');
        $this->assertDatabaseHas('clarifications', [
            'procurement_request_id' => $request->id,
            'response' => $reply,
            'status' => 'responded',
        ]);
        $this->assertDatabaseHas('integration_email_replies', [
            'provider_message_id' => 'gmail-requester-reply-1',
            'action' => 'clarification_response',
        ]);
    }

    public function test_requester_email_reply_is_idempotent(): void
    {
        [$request, $requester] = $this->makeClarificationRequest();
        $payload = [
            'message_id' => 'gmail-requester-reply-duplicate-1',
            'request_number' => $request->request_number,
            'sender_email' => $requester->email,
            'sender_authenticated' => true,
            'body' => 'The requested specifications are attached.',
        ];

        $this->postClarificationResponse($payload)->assertOk()->assertJsonPath('request_status', 'resubmitted');
        $this->postClarificationResponse($payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('integration_email_replies', 1);
        $this->assertDatabaseCount('clarifications', 1);
    }

    public function test_non_requester_cannot_respond_to_clarification_by_email(): void
    {
        [$request] = $this->makeClarificationRequest();

        $this->postClarificationResponse([
            'message_id' => 'gmail-unauthorized-requester-reply-1',
            'request_number' => $request->request_number,
            'sender_email' => 'someone-else@example.com',
            'sender_authenticated' => true,
            'body' => 'Here is the response.',
        ])->assertForbidden();

        $this->assertSame('clarification_required', $request->fresh()->status);
    }

    public function test_email_reply_requires_an_open_clarification(): void
    {
        [$request] = $this->makeSubmittedRequest();
        $requester = $request->requester;

        $this->postClarificationResponse([
            'message_id' => 'gmail-reply-no-clarification-1',
            'request_number' => $request->request_number,
            'sender_email' => $requester->email,
            'sender_authenticated' => true,
            'body' => 'Here is the response.',
        ])->assertConflict();
    }

    private function makeSubmittedRequest(): array
    {
        $requester = User::factory()->create(['role' => 'requester']);
        $approver = User::factory()->create(['role' => 'approver']);
        $request = ProcurementRequest::create([
            'request_number' => 'PR-2026-0099',
            'requester_id' => $requester->id,
            'department' => 'IT',
            'item_description' => 'Developer laptop',
            'quantity' => 1,
            'amount' => 125000,
            'justification' => 'Equipment for a new developer.',
            'status' => 'submitted',
            'current_approver_id' => $approver->id,
        ]);

        return [$request, $approver];
    }

    private function makeClarificationRequest(): array
    {
        [$request, $approver] = $this->makeSubmittedRequest();
        $request->update(['status' => 'clarification_required']);
        $request->clarifications()->create([
            'requested_by' => $approver->id,
            'question' => 'Please explain why this item is required.',
            'status' => 'pending',
        ]);

        return [$request->fresh(), $request->requester];
    }

    private function postDecision(array $payload, bool $includeSecret = true)
    {
        config(['procurement.n8n_webhook_secret' => $includeSecret ? 'local-test-secret' : null]);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($includeSecret) {
            $headers['HTTP_X_N8N_WEBHOOK_SECRET'] = 'local-test-secret';
        }

        return $this->call('POST', '/api/v1/approver/email-decision', [], [], [], $headers, $body);
    }

    private function postClarificationResponse(array $payload, bool $includeSecret = true)
    {
        config(['procurement.n8n_webhook_secret' => $includeSecret ? 'local-test-secret' : null]);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($includeSecret) {
            $headers['HTTP_X_N8N_WEBHOOK_SECRET'] = 'local-test-secret';
        }

        return $this->call('POST', '/api/v1/requester/email-clarification-response', [], [], [], $headers, $body);
    }
}
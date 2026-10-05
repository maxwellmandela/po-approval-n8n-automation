<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationEmailReply;
use App\Models\ProcurementRequest;
use App\Services\ProcurementWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class IntegrationController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        if (! $this->hasValidSecret($request)) {
            return response()->json(['message' => 'Invalid integration credentials.'], 403);
        }

        Log::info('n8n webhook received', ['event' => $request->input('event')]);

        return response()->json([
            'status' => 'accepted',
            'received_at' => now()->toISOString(),
            'event' => $request->input('event'),
        ]);
    }

    public function emailDecision(Request $request, ProcurementWorkflowService $workflow): JsonResponse
    {
        if (! config('procurement.n8n_webhook_secret')) {
            return response()->json(['message' => 'Email decisions are not configured.'], 503);
        }

        if (! $this->hasValidSecret($request)) {
            return response()->json(['message' => 'Invalid integration credentials.'], 403);
        }

        $data = $request->validate([
            'message_id' => ['required', 'string', 'max:255'],
            'request_number' => ['required', 'string', 'max:255'],
            'sender_email' => ['required', 'email', 'max:255'],
            'sender_authenticated' => ['required', 'accepted'],
            'action' => ['required', 'in:approve,reject,clarify'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);

        if (in_array($data['action'], ['reject', 'clarify'], true) && blank($data['comment'] ?? null)) {
            return response()->json(['message' => 'A comment is required for this action.'], 422);
        }

        return DB::transaction(function () use ($data, $workflow): JsonResponse {
            $procurementRequest = ProcurementRequest::where('request_number', $data['request_number'])
                ->lockForUpdate()
                ->first();

            if (! $procurementRequest) {
                return response()->json(['message' => 'Request not found.'], 404);
            }

            $existingReply = IntegrationEmailReply::where('provider_message_id', $data['message_id'])->first();

            if ($existingReply) {
                return response()->json([
                    'status' => 'already_processed',
                    'duplicate' => true,
                    'request_status' => $procurementRequest->status,
                ]);
            }

            $approver = $procurementRequest->currentApprover;

            if (! $approver || strcasecmp(trim($approver->email), trim($data['sender_email'])) !== 0) {
                return response()->json(['message' => 'Sender is not the assigned approver.'], 403);
            }

            try {
                match ($data['action']) {
                    'approve' => $workflow->approve($procurementRequest, $approver, $data['comment'] ?? ''),
                    'reject' => $workflow->reject($procurementRequest, $approver, $data['comment']),
                    'clarify' => $workflow->requestClarification($procurementRequest, $approver, $data['comment']),
                };
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            IntegrationEmailReply::create([
                'provider_message_id' => $data['message_id'],
                'procurement_request_id' => $procurementRequest->id,
                'sender_email' => strtolower(trim($data['sender_email'])),
                'action' => $data['action'],
                'comment' => $data['comment'] ?? null,
                'processed_at' => now(),
            ]);

            return response()->json([
                'status' => 'processed',
                'action' => $data['action'],
                'request_status' => $procurementRequest->fresh()->status,
            ]);
        });
    }

    public function clarificationResponse(Request $request, ProcurementWorkflowService $workflow): JsonResponse
    {
        if (! config('procurement.n8n_webhook_secret')) {
            return response()->json(['message' => 'Email responses are not configured.'], 503);
        }

        if (! $this->hasValidSecret($request)) {
            return response()->json(['message' => 'Invalid integration credentials.'], 403);
        }

        $data = $request->validate([
            'message_id' => ['required', 'string', 'max:255'],
            'request_number' => ['required', 'string', 'max:255'],
            'sender_email' => ['required', 'email', 'max:255'],
            'sender_authenticated' => ['required', 'accepted'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        return DB::transaction(function () use ($data, $workflow): JsonResponse {
            $procurementRequest = ProcurementRequest::where('request_number', $data['request_number'])
                ->lockForUpdate()
                ->first();

            if (! $procurementRequest) {
                return response()->json(['message' => 'Request not found.'], 404);
            }

            $existingReply = IntegrationEmailReply::where('provider_message_id', $data['message_id'])->first();

            if ($existingReply) {
                if ($existingReply->procurement_request_id !== $procurementRequest->id) {
                    return response()->json(['message' => 'Message ID is already associated with another request.'], 409);
                }

                return response()->json([
                    'status' => 'already_processed',
                    'duplicate' => true,
                    'request_status' => $procurementRequest->status,
                ]);
            }

            $requester = $procurementRequest->requester;

            if (! $requester || strcasecmp(trim($requester->email), trim($data['sender_email'])) !== 0) {
                return response()->json(['message' => 'Sender is not the requester for this request.'], 403);
            }

            if ($procurementRequest->status !== 'clarification_required') {
                return response()->json(['message' => 'Request does not have an open clarification.'], 409);
            }

            $clarification = $procurementRequest->clarifications()
                ->where('status', 'pending')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $clarification) {
                return response()->json(['message' => 'Request does not have an open clarification.'], 409);
            }

            try {
                $workflow->respondToClarification($clarification, $requester, $data['body'], $procurementRequest);
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            IntegrationEmailReply::create([
                'provider_message_id' => $data['message_id'],
                'procurement_request_id' => $procurementRequest->id,
                'sender_email' => strtolower(trim($data['sender_email'])),
                'action' => 'clarification_response',
                'comment' => $data['body'],
                'processed_at' => now(),
            ]);

            return response()->json([
                'status' => 'processed',
                'action' => 'clarification_response',
                'request_status' => $procurementRequest->fresh()->status,
            ]);
        });
    }

    private function hasValidSecret(Request $request): bool
    {
        $secret = config('procurement.n8n_webhook_secret');
        $provided = $request->header('X-N8N-Webhook-Secret');

        return is_string($secret)
            && $secret !== ''
            && is_string($provided)
            && hash_equals($secret, $provided);
    }
}

<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\Clarification;
use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProcurementWorkflowService
{
    public function __construct(protected IntegrationEventService $integrationEventService)
    {
    }

    public function submit(ProcurementRequest $request, User $actor): ProcurementRequest
    {
        $this->validateStatusTransition($request->status, 'submitted', 'submit');

        $this->validateBudget($request);

        return DB::transaction(function () use ($request, $actor) {
            if (empty($request->request_number)) {
                $request->request_number = $this->generateRequestNumber();
            }

            $request->update([
                'request_number' => $request->request_number,
                'status' => 'submitted',
                'submitted_at' => now(),
                'current_approver_id' => $this->determineApprover($request)?->id,
            ]);

            $this->logAction($request, $actor, 'Request submitted', 'Request submitted for review.', ['status' => 'submitted']);
            $this->integrationEventService->dispatch('procurement.request.submitted', $request, $actor, [
                'department' => $request->department,
                'requester' => ['name' => $request->requester->name, 'email' => $request->requester->email],
                'approver' => $this->determineApprover($request) ? [
                    'name' => $this->determineApprover($request)->name,
                    'email' => $this->determineApprover($request)->email,
                ] : null,
            ]);

            return $request->fresh();
        });
    }

    public function requestClarification(ProcurementRequest $request, User $actor, string $question): Clarification
    {
        $this->validateStatusTransition($request->status, 'clarification_required', 'request clarification');

        return DB::transaction(function () use ($request, $actor, $question) {
            $clarification = $request->clarifications()->create([
                'requested_by' => $actor->id,
                'question' => $question,
                'status' => 'pending',
            ]);

            $request->update([
                'status' => 'clarification_required',
                'current_approver_id' => $actor->id,
            ]);

            $this->logAction($request, $actor, 'Clarification requested', $question, ['clarification_id' => $clarification->id]);
            $this->integrationEventService->dispatch('procurement.request.clarification_requested', $request, $actor, [
                'clarification' => ['question' => $question],
            ]);

            return $clarification;
        });
    }

    public function respondToClarification(Clarification $clarification, User $actor, string $response, ProcurementRequest $request): Clarification
    {
        if ($actor->id !== $request->requester_id) {
            throw new InvalidArgumentException('Only the requester can respond to clarification.');
        }

        return DB::transaction(function () use ($clarification, $actor, $response, $request) {
            $clarification->update([
                'response' => $response,
                'responded_at' => now(),
                'status' => 'responded',
            ]);

            $request->update([
                'status' => 'resubmitted',
                'current_approver_id' => $request->current_approver_id ?? $this->determineApprover($request)?->id,
            ]);

            $this->logAction($request, $actor, 'Clarification responded', $response, ['clarification_id' => $clarification->id]);
            $this->integrationEventService->dispatch('procurement.request.resubmitted', $request, $actor, [
                'clarification' => ['response' => $response],
            ]);

            return $clarification->fresh();
        });
    }

    public function approve(ProcurementRequest $request, User $actor, string $comments = ''): Approval
    {
        if ($actor->id === $request->requester_id) {
            throw new AuthorizationException('A requester cannot approve their own request.');
        }

        $this->validateStatusTransition($request->status, 'approved', 'approve');

        return DB::transaction(function () use ($request, $actor, $comments) {
            $approval = $request->approvals()->create([
                'approver_id' => $actor->id,
                'action' => 'approved',
                'comments' => $comments,
            ]);

            $request->update([
                'status' => 'approved',
                'current_approver_id' => $actor->id,
            ]);

            $this->logAction($request, $actor, 'Approved', $comments ?: 'Request approved.', ['approval_id' => $approval->id]);
            $this->integrationEventService->dispatch('procurement.request.approved', $request, $actor, [
                'approval' => ['comments' => $comments],
            ]);

            return $approval;
        });
    }

    public function reject(ProcurementRequest $request, User $actor, string $reason): Approval
    {
        if ($actor->id === $request->requester_id) {
            throw new AuthorizationException('A requester cannot reject their own request.');
        }

        $this->validateStatusTransition($request->status, 'rejected', 'reject');

        return DB::transaction(function () use ($request, $actor, $reason) {
            $approval = $request->approvals()->create([
                'approver_id' => $actor->id,
                'action' => 'rejected',
                'comments' => $reason,
            ]);

            $request->update([
                'status' => 'rejected',
                'current_approver_id' => $actor->id,
            ]);

            $this->logAction($request, $actor, 'Rejected', $reason, ['approval_id' => $approval->id]);
            $this->integrationEventService->dispatch('procurement.request.rejected', $request, $actor, [
                'approval' => ['reason' => $reason],
            ]);

            return $approval;
        });
    } 

    public function createPurchaseOrder(ProcurementRequest $request, User $actor): PurchaseOrder
    {
        $this->validateStatusTransition($request->status, 'po_created', 'po created');

        return DB::transaction(function () use ($request, $actor) {
            $poNumber = $this->generatePoNumber();

            $purchaseOrder = $request->purchaseOrder()->create([
                'po_number' => $poNumber,
                'created_by' => $actor->id,
                'status' => 'created',
            ]);

            $request->update([
                'status' => 'po_created',
            ]);

            $this->logAction($request, $actor, 'PO created', 'Purchase order created.', ['po_number' => $poNumber]);

            return $purchaseOrder;
        });
    }

    public function validateBudget(ProcurementRequest $request): void
    {
        $budget = Budget::where('department', $request->department)->first();

        if (! $budget) {
            throw ValidationException::withMessages([
                'department' => 'No budget is configured for this department.',
            ]);
        }

        $remaining = (float) $budget->budget_amount - (float) $budget->committed_amount - (float) $request->amount;

        if ($remaining < 0) {
            throw ValidationException::withMessages([
                'amount' => 'This request exceeds the remaining department budget.',
            ]);
        }
    }

    public function determineApprover(ProcurementRequest $request): ?User
    {
        $threshold = (float) config('procurement.approval_threshold', 100000);

        if ((float) $request->amount <= $threshold) {
            return User::where('role', 'procurement')->first();
        }

        return User::where('role', 'approver')->first();
    }

    public function generateRequestNumber(): string
    {
        $year = now()->year;
        $prefix = 'PR-'.$year.'-';
        $last = ProcurementRequest::where('request_number', 'like', $prefix.'%')->orderByDesc('id')->first();

        $number = $last ? ((int) str_replace($prefix, '', $last->request_number)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $number);
    }

    public function generatePoNumber(): string
    {
        $year = now()->year;
        $prefix = 'PO-'.$year.'-';
        $last = PurchaseOrder::where('po_number', 'like', $prefix.'%')->orderByDesc('id')->first();

        $number = $last ? ((int) str_replace($prefix, '', $last->po_number)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $number);
    }

    protected function validateStatusTransition(string $current, string $target, string $action): void
    {
        $allowed = match ($target) {
            'submitted' => ['draft'],
            'under_review' => ['submitted', 'resubmitted'],
            'clarification_required' => ['under_review', 'submitted', 'resubmitted'],
            'resubmitted' => ['clarification_required'],
            'approved' => ['submitted', 'under_review', 'resubmitted'],
            'rejected' => ['submitted', 'under_review', 'resubmitted'],
            'po_created' => ['approved'],
            'recorded' => ['po_created'],
            default => []
        };

        if (! in_array($current, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Invalid transition: %s -> %s for %s.', $current, $target, $action));
        }
    }

    protected function logAction(ProcurementRequest $request, User $actor, string $action, string $description, array $metadata = []): void
    {
        $request->auditLogs()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}

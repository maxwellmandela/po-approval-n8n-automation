<?php

namespace App\Policies;

use App\Models\ProcurementRequest;
use App\Models\User;

class ProcurementRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['requester', 'procurement', 'approver', 'admin'], true);
    }

    public function view(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $user->id === $procurementRequest->requester_id
            || in_array($user->role, ['procurement', 'approver', 'admin'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['requester', 'admin'], true);
    }

    public function update(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $user->id === $procurementRequest->requester_id && $procurementRequest->status === 'draft';
    }

    public function submit(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $user->id === $procurementRequest->requester_id && in_array($procurementRequest->status, ['draft', 'resubmitted'], true);
    }

    public function review(User $user, ProcurementRequest $procurementRequest): bool
    {
        return in_array($user->role, ['procurement', 'approver', 'admin'], true);
    }

    public function respond(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $user->id === $procurementRequest->requester_id && in_array($procurementRequest->status, ['clarification_required', 'resubmitted'], true);
    }

    public function approve(User $user, ProcurementRequest $procurementRequest): bool
    {
        return in_array($user->role, ['procurement', 'approver', 'admin'], true)
            && $user->id !== $procurementRequest->requester_id;
    }
}

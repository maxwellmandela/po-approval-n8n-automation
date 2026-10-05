<?php

namespace App\Http\Controllers;

use App\Models\Clarification;
use App\Models\ProcurementRequest;
use App\Services\ProcurementWorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProcurementReviewController extends Controller
{
    use AuthorizesRequests;
    public function queue(): View
    {
        $this->authorize('viewAny', ProcurementRequest::class);

        $requests = ProcurementRequest::with(['requester', 'vendor', 'auditLogs.user'])
            ->whereIn('status', ['submitted', 'under_review', 'resubmitted', 'clarification_required'])
            ->latest('updated_at')
            ->get();

        return view('review.queue', ['requests' => $requests]);
    }

    public function show(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('review', $procurementRequest);

        return view('review.show', [
            'request' => $procurementRequest->load(['requester', 'vendor', 'approvals.approver', 'auditLogs.user', 'clarifications']),
            'budget' => $procurementRequest->budgetSnapshot(),
        ]);
    }

    public function approve(ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('approve', $procurementRequest);

        app(ProcurementWorkflowService::class)->approve($procurementRequest, auth()->user(), 'Approved by reviewer.');

        return redirect()->route('review.show', $procurementRequest)->with('success', 'Request approved.');
    }

    public function clarify(ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('review', $procurementRequest);

        $question = request('question');

        if (! $question) {
            return back()->withErrors(['question' => 'A clarification question is required.']);
        }

        app(ProcurementWorkflowService::class)->requestClarification($procurementRequest, auth()->user(), $question);

        return redirect()->route('review.queue')->with('success', 'Clarification requested.');
    }

    public function reject(ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('approve', $procurementRequest);

        $reason = request('reason');

        if (! $reason) {
            return back()->withErrors(['reason' => 'A rejection reason is required.']);
        }

        app(ProcurementWorkflowService::class)->reject($procurementRequest, auth()->user(), $reason);

        return redirect()->route('review.show', $procurementRequest)->with('success', 'Request rejected.');
    }
}

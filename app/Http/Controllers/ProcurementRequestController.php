<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClarificationResponseRequest;
use App\Http\Requests\StoreProcurementRequestRequest;
use App\Models\Budget;
use App\Models\Clarification;
use App\Models\ProcurementRequest;
use App\Models\Vendor;
use App\Services\ProcurementWorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcurementRequestController extends Controller
{
    use AuthorizesRequests;
    public function index(): View
    {
        $query = ProcurementRequest::with(['requester', 'vendor', 'auditLogs.user'])
            ->latest('updated_at');

        if (auth()->user()->role === 'requester') {
            $query->where('requester_id', auth()->id());
        }

        return view('requests.index', [
            'requests' => $query->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ProcurementRequest::class);

        return view('requests.create', [
            'vendors' => Vendor::orderBy('name')->get(),
            'budgets' => Budget::orderBy('department')->get(),
        ]);
    }

    public function store(StoreProcurementRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', ProcurementRequest::class);

        $data = $request->validated();
        $submitImmediately = ($data['status'] ?? 'draft') === 'submitted';
        $data['requester_id'] = auth()->id();
        $data['supporting_documents'] = $this->storeFiles($request, 'supporting_documents');
        $data['status'] = 'draft';
        $data['vendor_name'] = $data['vendor_name'] ?? ($data['vendor_id'] ? Vendor::find($data['vendor_id'])?->name : null);

        $procurementRequest = ProcurementRequest::create($data);
        $procurementRequest->request_number = app(ProcurementWorkflowService::class)->generateRequestNumber();
        $procurementRequest->save();

        if ($submitImmediately) {
            app(ProcurementWorkflowService::class)->submit($procurementRequest, auth()->user());
        }

        $message = $submitImmediately ? 'Request submitted for approval.' : 'Draft saved.';

        return redirect()->route('requests.show', $procurementRequest)->with('success', $message);
    }

    public function show(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('view', $procurementRequest);

        $procurementRequest->load(['requester', 'vendor', 'clarifications', 'approvals.approver', 'purchaseOrder', 'auditLogs.user']);

        return view('requests.show', [
            'request' => $procurementRequest,
            'budget' => $procurementRequest->budgetSnapshot(),
        ]);
    }

    public function submit(ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('submit', $procurementRequest);

        app(ProcurementWorkflowService::class)->submit($procurementRequest, auth()->user());

        return redirect()->route('requests.show', $procurementRequest)->with('success', 'Request submitted for review.');
    }

    public function respondToClarification(Clarification $clarification, ClarificationResponseRequest $request): RedirectResponse
    {
        $procurementRequest = $clarification->procurementRequest;
        $this->authorize('respond', $procurementRequest);

        $responseDocuments = $this->storeFiles($request, 'response_documents');
        $clarification->update([
            'response' => $request->input('response'),
            'response_documents' => $responseDocuments,
            'status' => 'responded',
            'responded_at' => now(),
        ]);

        app(ProcurementWorkflowService::class)->respondToClarification($clarification, auth()->user(), $request->input('response'), $procurementRequest);

        return redirect()->route('requests.show', $procurementRequest)->with('success', 'Clarification response submitted.');
    }

    protected function storeFiles(Request $request, string $field): array
    {
        $paths = [];

        if (! $request->hasFile($field)) {
            return $paths;
        }

        foreach ($request->file($field) as $file) {
            $path = $file->store('procurement-documents', 'public');
            if ($path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ProcurementWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRequester(): User
    {
        return User::factory()->create([
            'name' => 'Jane Requester',
            'email' => 'jane@example.com',
            'role' => 'requester',
        ]);
    }

    protected function makeProcurementManager(): User
    {
        return User::factory()->create([
            'name' => 'Procurement Manager',
            'email' => 'procurement@example.com',
            'role' => 'procurement',
        ]);
    }

    protected function makeFinanceApprover(): User
    {
        return User::factory()->create([
            'name' => 'Finance Approver',
            'email' => 'finance@example.com',
            'role' => 'approver',
        ]);
    }

    protected function makeBudget(string $department = 'Operations'): Budget
    {
        return Budget::create([
            'department' => $department,
            'budget_amount' => 500000,
            'committed_amount' => 250000,
        ]);
    }

    public function test_requester_can_create_a_draft(): void
    {
        $requester = $this->makeRequester();
        $vendor = Vendor::create(['name' => 'Acme Office Supplies']);

        $this->followingRedirects()
            ->actingAs($requester)
            ->post(route('requests.store'), [
                'department' => 'Operations',
                'vendor_id' => $vendor->id,
                'item_description' => 'Office chairs',
                'quantity' => 4,
                'amount' => 55000,
                'required_by' => '2026-11-10',
                'justification' => 'Staff expansion',
                'status' => 'draft',
            ])
            ->assertOk()
            ->assertSee('Draft saved.');

        $this->assertDatabaseHas('procurement_requests', [
            'requester_id' => $requester->id,
            'status' => 'draft',
            'department' => 'Operations',
        ]);
    }

    public function test_requester_can_create_and_submit_a_request_in_one_step(): void
    {
        $requester = $this->makeRequester();
        $approver = $this->makeFinanceApprover();
        $vendor = Vendor::create(['name' => 'Acme Office Supplies']);
        Budget::create([
            'department' => 'IT',
            'budget_amount' => 650000,
            'committed_amount' => 210000,
        ]);

        $this->followingRedirects()
            ->actingAs($requester)
            ->post(route('requests.store'), [
                'department' => 'IT',
                'vendor_id' => $vendor->id,
                'item_description' => 'HP Probook 2027',
                'quantity' => 1,
                'amount' => 125000,
                'required_by' => '2026-10-07',
                'justification' => 'Laptop for a new developer.',
                'status' => 'submitted',
            ])
            ->assertOk()
            ->assertSee('Request submitted for approval.');

        $this->assertDatabaseHas('procurement_requests', [
            'requester_id' => $requester->id,
            'department' => 'IT',
            'item_description' => 'HP Probook 2027',
            'status' => 'submitted',
            'current_approver_id' => $approver->id,
        ]);
    }

    public function test_requester_can_submit_a_request(): void
    {
        $requester = $this->makeRequester();
        $vendor = Vendor::create(['name' => 'Acme Office Supplies']);
        $this->makeBudget();

        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'vendor_id' => $vendor->id,
            'vendor_name' => 'Acme Office Supplies',
            'item_description' => 'Office chairs',
            'quantity' => 4,
            'amount' => 50000,
            'justification' => 'Staff expansion',
            'required_by' => '2026-11-10',
            'status' => 'draft',
        ]);

        $service = app(ProcurementWorkflowService::class);
        $service->submit($request, $requester);

        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->request_number);
    }

    public function test_approval_threshold_determines_required_approver(): void
    {
        $service = app(ProcurementWorkflowService::class);
        $procurement = $this->makeProcurementManager();

        $low = new ProcurementRequest([
            'department' => 'Operations',
            'amount' => 95000,
        ]);
        $this->assertSame($procurement->id, $service->determineApprover($low)?->id ?? null);

        $high = new ProcurementRequest([
            'department' => 'Operations',
            'amount' => 150000,
        ]);
        $finance = $this->makeFinanceApprover();
        $this->assertSame($finance->id, $service->determineApprover($high)?->id ?? null);
    }

    public function test_reviewer_can_request_clarification(): void
    {
        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $vendor = Vendor::create(['name' => 'Nairobi Industrial Solutions']);
        $this->makeBudget();

        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'vendor_id' => $vendor->id,
            'vendor_name' => 'Nairobi Industrial Solutions',
            'item_description' => 'Laptop refresh',
            'quantity' => 1,
            'amount' => 70000,
            'justification' => 'Role coverage',
            'required_by' => '2026-11-10',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $service = app(ProcurementWorkflowService::class);
        $service->requestClarification($request, $procurement, 'Please provide a quote');

        $this->assertSame('clarification_required', $request->fresh()->status);
        $this->assertDatabaseHas('clarifications', [
            'procurement_request_id' => $request->id,
            'status' => 'pending',
        ]);
    }

    public function test_requester_can_respond_and_resubmit(): void
    {
        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $vendor = Vendor::create(['name' => 'East Africa Logistics']);
        $this->makeBudget();

        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'vendor_id' => $vendor->id,
            'vendor_name' => 'East Africa Logistics',
            'item_description' => 'Shipment service',
            'quantity' => 2,
            'amount' => 45000,
            'justification' => 'Logistics support',
            'required_by' => '2026-11-10',
            'status' => 'clarification_required',
            'submitted_at' => now(),
        ]);

        $clarification = $request->clarifications()->create([
            'requested_by' => $procurement->id,
            'question' => 'Please provide a quote',
            'status' => 'pending',
        ]);

        $service = app(ProcurementWorkflowService::class);
        $service->respondToClarification($clarification, $requester, 'Attached quote for approval.', $request);

        $this->assertSame('resubmitted', $request->fresh()->status);
        $this->assertSame('responded', $clarification->fresh()->status);
    }

    public function test_reviewer_can_approve(): void
    {
        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $vendor = Vendor::create(['name' => 'Acme Office Supplies']);
        $this->makeBudget();

        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'vendor_id' => $vendor->id,
            'vendor_name' => 'Acme Office Supplies',
            'item_description' => 'Office chairs',
            'quantity' => 4,
            'amount' => 60000,
            'justification' => 'Staff expansion',
            'required_by' => '2026-11-10',
            'status' => 'under_review',
            'submitted_at' => now(),
            'current_approver_id' => $procurement->id,
        ]);

        $service = app(ProcurementWorkflowService::class);
        $service->approve($request, $procurement, 'Looks good');

        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_reviewer_can_approve_a_submitted_request(): void
    {
        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'item_description' => 'Submitted request',
            'quantity' => 1,
            'amount' => 5000,
            'justification' => 'Need',
            'status' => 'submitted',
        ]);

        app(ProcurementWorkflowService::class)->approve($request, $procurement, 'Looks good');

        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_reviewer_can_reject_a_submitted_request(): void
    {
        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'item_description' => 'Submitted request',
            'quantity' => 1,
            'amount' => 5000,
            'justification' => 'Need',
            'status' => 'submitted',
        ]);

        app(ProcurementWorkflowService::class)->reject($request, $procurement, 'Not within budget priorities');

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertDatabaseHas('approvals', [
            'procurement_request_id' => $request->id,
            'approver_id' => $procurement->id,
            'action' => 'rejected',
        ]);
    }

    public function test_rejected_request_cannot_be_approved(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'item_description' => 'Rejected request',
            'quantity' => 1,
            'amount' => 5000,
            'justification' => 'Need',
            'status' => 'rejected',
        ]);

        app(ProcurementWorkflowService::class)->approve($request, $procurement, 'Wrong flow');
    }

    public function test_clarification_required_request_cannot_be_approved_before_resubmission(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'item_description' => 'Need clarification first',
            'quantity' => 1,
            'amount' => 6000,
            'justification' => 'Need',
            'status' => 'clarification_required',
        ]);

        app(ProcurementWorkflowService::class)->approve($request, $procurement, 'Still awaiting response');
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        $this->expectException(\AuthorizationException::class);

        $requester = $this->makeRequester();
        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'item_description' => 'Self approval attempt',
            'quantity' => 1,
            'amount' => 2000,
            'justification' => 'Need',
            'status' => 'under_review',
        ]);

        app(ProcurementWorkflowService::class)->approve($request, $requester, 'Nope');
    }

    public function test_po_number_is_generated_and_state_changes(): void
    {
        $requester = $this->makeRequester();
        $procurement = $this->makeProcurementManager();
        $request = ProcurementRequest::create([
            'requester_id' => $requester->id,
            'department' => 'Operations',
            'item_description' => 'Bulk order',
            'quantity' => 3,
            'amount' => 80000,
            'justification' => 'Needed',
            'status' => 'approved',
        ]);

        $service = app(ProcurementWorkflowService::class);
        $po = $service->createPurchaseOrder($request, $procurement);

        $this->assertNotNull($po->po_number);
        $this->assertSame('po_created', $request->fresh()->status);
    }
}

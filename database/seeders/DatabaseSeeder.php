<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Clarification;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $requester = User::updateOrCreate(
            ['email' => 'jane@example.com'],
            [
                'name' => 'Jane Requester',
                'password' => Hash::make('password'),
                'role' => 'requester',
            ]
        );

        $procurement = User::updateOrCreate(
            ['email' => 'procurement@example.com'],
            [
                'name' => 'Procurement Manager',
                'password' => Hash::make('password'),
                'role' => 'procurement',
            ]
        );

        $finance = User::updateOrCreate(
            ['email' => 'finance@example.com'],
            [
                'name' => 'Finance Approver',
                'password' => Hash::make('password'),
                'role' => 'approver',
            ]
        );

        foreach (['Acme Office Supplies', 'Nairobi Industrial Solutions', 'East Africa Logistics'] as $vendorName) {
            Vendor::updateOrCreate(['name' => $vendorName], [
                'contact_name' => 'Operations Lead',
                'email' => strtolower(str_replace(' ', '.', $vendorName)).'@example.com',
                'phone' => '+254700000000',
            ]);
        }

        foreach ([
            ['Operations', 500000, 250000],
            ['Finance', 300000, 100000],
            ['IT', 650000, 210000],
            ['Procurement', 180000, 40000],
        ] as [$department, $budgetAmount, $committedAmount]) {
            Budget::updateOrCreate(
                ['department' => $department],
                ['budget_amount' => $budgetAmount, 'committed_amount' => $committedAmount]
            );
        }

        $vendor = Vendor::first();
        $industrial = Vendor::where('name', 'Nairobi Industrial Solutions')->first();
        $logistics = Vendor::where('name', 'East Africa Logistics')->first();

        $draftRequest = ProcurementRequest::updateOrCreate(
            ['request_number' => 'PR-2026-0001'],
            [
                'requester_id' => $requester->id,
                'department' => 'Operations',
                'vendor_id' => $vendor?->id,
                'vendor_name' => $vendor?->name,
                'item_description' => 'Draft request for office chairs',
                'quantity' => 4,
                'amount' => 65000,
                'justification' => 'Additional seating for new team members.',
                'required_by' => now()->addDays(15)->toDateString(),
                'status' => 'draft',
                'current_approver_id' => $procurement->id,
            ]
        );

        $submittedRequest = ProcurementRequest::updateOrCreate(
            ['request_number' => 'PR-2026-0002'],
            [
                'requester_id' => $requester->id,
                'department' => 'IT',
                'vendor_id' => $industrial?->id,
                'vendor_name' => $industrial?->name,
                'item_description' => 'Laptop refresh for the support team',
                'quantity' => 6,
                'amount' => 135000,
                'justification' => 'Refresh legacy hardware to improve stability and productivity.',
                'required_by' => now()->addDays(20)->toDateString(),
                'status' => 'under_review',
                'current_approver_id' => $procurement->id,
                'submitted_at' => now()->subDays(2),
            ]
        );

        $clarificationRequest = ProcurementRequest::updateOrCreate(
            ['request_number' => 'PR-2026-0003'],
            [
                'requester_id' => $requester->id,
                'department' => 'Operations',
                'vendor_id' => $logistics?->id,
                'vendor_name' => $logistics?->name,
                'item_description' => 'Regional courier contract',
                'quantity' => 1,
                'amount' => 90000,
                'justification' => 'Support recurring distribution needs across the region.',
                'required_by' => now()->addDays(30)->toDateString(),
                'status' => 'clarification_required',
                'current_approver_id' => $procurement->id,
                'submitted_at' => now()->subDays(6),
            ]
        );

        Clarification::updateOrCreate(
            [
                'procurement_request_id' => $clarificationRequest->id,
                'requested_by' => $procurement->id,
            ],
            [
                'question' => 'Please provide the quote for the regional service and confirm the SLA terms.',
                'status' => 'pending',
            ]
        );

        $approvedRequest = ProcurementRequest::updateOrCreate(
            ['request_number' => 'PR-2026-0004'],
            [
                'requester_id' => $requester->id,
                'department' => 'Finance',
                'vendor_id' => $vendor?->id,
                'vendor_name' => $vendor?->name,
                'item_description' => 'Accounting software subscription',
                'quantity' => 1,
                'amount' => 78000,
                'justification' => 'License renewal for financial operations compliance tools.',
                'required_by' => now()->addDays(12)->toDateString(),
                'status' => 'approved',
                'current_approver_id' => $finance->id,
                'submitted_at' => now()->subDays(10),
            ]
        );

        $rejectedRequest = ProcurementRequest::updateOrCreate(
            ['request_number' => 'PR-2026-0005'],
            [
                'requester_id' => $requester->id,
                'department' => 'Procurement',
                'vendor_id' => $vendor?->id,
                'vendor_name' => $vendor?->name,
                'item_description' => 'Premium branded merchandise',
                'quantity' => 500,
                'amount' => 240000,
                'justification' => 'Marketing and event materials for brand awareness.',
                'required_by' => now()->addDays(25)->toDateString(),
                'status' => 'rejected',
                'current_approver_id' => $procurement->id,
                'submitted_at' => now()->subDays(15),
            ]
        );

        foreach ([$draftRequest, $submittedRequest, $clarificationRequest, $approvedRequest, $rejectedRequest] as $request) {
            $request->auditLogs()->firstOrCreate([
                'action' => 'Request created',
                'description' => 'Initial request created.',
            ], [
                'user_id' => $request->requester_id,
                'metadata' => ['source' => 'seed'],
            ]);
        }
    }
}

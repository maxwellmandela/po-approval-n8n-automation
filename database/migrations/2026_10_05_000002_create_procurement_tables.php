<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->decimal('budget_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique()->nullable();
            $table->foreignId('requester_id')->constrained('users');
            $table->string('department');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->string('vendor_name')->nullable();
            $table->string('item_description');
            $table->integer('quantity')->default(1);
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('justification');
            $table->date('required_by')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('current_approver_id')->nullable()->constrained('users');
            $table->json('supporting_documents')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users');
            $table->string('action');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('clarifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->text('question');
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('status')->default('pending');
            $table->json('response_documents')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->string('po_number')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('status')->default('created');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->nullable()->constrained('procurement_requests')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('clarifications');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('procurement_requests');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('vendors');
    }
};

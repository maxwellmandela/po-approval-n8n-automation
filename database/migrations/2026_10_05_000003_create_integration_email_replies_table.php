<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_email_replies', function (Blueprint $table) {
            $table->id();
            $table->string('provider_message_id')->unique();
            $table->foreignId('procurement_request_id')->constrained()->cascadeOnDelete();
            $table->string('sender_email');
            $table->string('action');
            $table->text('comment')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_email_replies');
    }
};
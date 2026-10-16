<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->boolean('signature_valid')->default(false);
            $table->string('event_id')->nullable();
            $table->string('event_type')->nullable();
            $table->string('response_token')->nullable();
            $table->longText('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['form_id', 'received_at']);
            $table->index('received_at');
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('form_versions');
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('response_token');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('landed_at')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->enum('status', ['pending_review', 'approved', 'rejected'])->default('approved');
            $table->enum('source', ['webhook', 'backfill', 'reconcile'])->default('webhook');
            $table->json('raw');
            $table->json('info')->nullable();
            $table->decimal('points', 10, 2)->nullable();
            $table->decimal('max_points', 10, 2)->nullable();
            $table->decimal('overall_pct', 5, 2)->nullable();
            $table->enum('rag', ['green', 'amber', 'red'])->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'response_token']);
            $table->index(['hospital_id', 'status', 'submitted_at']);
            $table->index(['form_id', 'submitted_at']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('typeform_field_id');
            $table->foreignId('form_field_id')->nullable()->constrained('form_fields')->nullOnDelete();
            $table->string('answer_type');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 10, 2)->nullable();
            $table->json('value_json')->nullable();
            $table->decimal('points', 8, 2)->nullable();
            $table->decimal('max_points', 8, 2)->nullable();
            $table->timestamps();
            $table->index('submission_id');
            $table->index('form_field_id');
        });

        Schema::create('submission_section_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('section_name');
            $table->unsignedInteger('display_order')->default(0);
            $table->decimal('points', 10, 2)->default(0);
            $table->decimal('max_points', 10, 2)->default(0);
            $table->decimal('pct', 5, 2)->nullable();
            $table->decimal('weight', 6, 2)->default(1.00);
            $table->timestamps();
            $table->unique(['submission_id', 'section_id']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_section_scores');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('webhook_deliveries');
    }
};

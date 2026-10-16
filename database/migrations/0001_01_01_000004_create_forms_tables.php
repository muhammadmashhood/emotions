<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('typeform_connection_id')->constrained('typeform_connections');
            $table->string('typeform_form_id')->unique();
            $table->string('title');
            $table->string('webhook_tag');
            $table->text('webhook_secret');
            $table->enum('webhook_status', ['active', 'missing', 'disabled', 'error', 'unregistered'])->default('unregistered');
            $table->timestamp('webhook_checked_at')->nullable();
            $table->text('webhook_last_error')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->json('settings');
            $table->enum('status', ['active', 'unlinked'])->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('definition');
            $table->string('definition_hash', 64);
            $table->boolean('needs_review')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'version_number']);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('form_versions')->nullOnDelete();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source_ref')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->decimal('weight', 6, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->string('typeform_field_id');
            $table->string('typeform_ref')->nullable();
            $table->text('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_scored')->default(false);
            $table->integer('scale_min')->nullable();
            $table->integer('scale_max')->nullable();
            $table->json('choices')->nullable();
            $table->string('info_key')->nullable();
            $table->boolean('is_new_in_version')->default(false);
            $table->timestamps();
            $table->unique(['form_version_id', 'typeform_field_id']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('sections');
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('forms');
    }
};

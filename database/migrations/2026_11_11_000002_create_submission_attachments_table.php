<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('answer_typeform_field_id')->nullable();
            $table->enum('source', ['typeform', 'upload']);
            $table->string('disk', 32)->default('local');
            $table->string('path', 512);
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['submission_id', 'answer_typeform_field_id'], 'submission_attachments_answer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_attachments');
    }
};

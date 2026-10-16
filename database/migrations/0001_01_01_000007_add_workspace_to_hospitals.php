<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('typeform_workspace_id')->nullable()->after('require_review');
            $table->string('typeform_workspace_name')->nullable()->after('typeform_workspace_id');
            $table->index('typeform_workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropIndex(['typeform_workspace_id']);
            $table->dropColumn(['typeform_workspace_id', 'typeform_workspace_name']);
        });
    }
};

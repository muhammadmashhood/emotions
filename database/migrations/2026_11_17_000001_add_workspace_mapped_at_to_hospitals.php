<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->timestamp('typeform_workspace_mapped_at')->nullable()->after('typeform_workspace_name');
        });

        // Existing mappings start counting from now, so forms that already exist in the workspace are never auto-linked.
        DB::table('hospitals')->whereNotNull('typeform_workspace_id')->update(['typeform_workspace_mapped_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn('typeform_workspace_mapped_at');
        });
    }
};

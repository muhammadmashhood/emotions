<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance pass (milestone 8): covering index so the "weakest questions" and "yes/no compliance"
 * aggregates are answered from the index alone (verified with EXPLAIN on 50 000 evaluations / 4 M answers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->index(['submission_id', 'typeform_field_id', 'points', 'max_points'], 'answers_scoring_covering_index');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex('answers_scoring_covering_index');
        });
    }
};

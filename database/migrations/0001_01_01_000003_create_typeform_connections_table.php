<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('typeform_connections', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('base_url')->default('https://api.typeform.com');
            $table->text('access_token');
            $table->string('account_alias')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('typeform_connections');
    }
};

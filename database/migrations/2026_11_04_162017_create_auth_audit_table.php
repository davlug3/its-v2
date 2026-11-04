<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_audit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username_attempted', 255)->nullable();
            $table->string('event', 40);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'idx_auth_audit_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_audit');
    }
};

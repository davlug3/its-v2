<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->string('action', 80);
            $table->string('entity', 80);
            $table->unsignedInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_user_id', 'created_at'], 'idx_admin_audit_actor_time');
            $table->index(['entity', 'entity_id'], 'idx_admin_audit_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit');
    }
};

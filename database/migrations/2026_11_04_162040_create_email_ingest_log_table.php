<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_ingest_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('message_id', 255)->nullable();
            $table->string('from_email', 255)->nullable();
            $table->string('subject', 500)->nullable();
            $table->unsignedInteger('ticket_id')->nullable();
            $table->string('status', 40);
            $table->text('error')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->useCurrent();

            $table->unique('message_id', 'uq_email_message_id');
            $table->index(['status', 'processed_at'], 'idx_email_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_ingest_log');
    }
};

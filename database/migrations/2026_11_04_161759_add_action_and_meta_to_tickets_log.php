<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets_log', function (Blueprint $table) {
            $table->string('action', 50)->nullable()->after('data');
            $table->json('meta')->nullable()->after('action');

            $table->index('ticket_id', 'idx_tickets_log_ticket_id');
            $table->index('created_uid', 'idx_tickets_log_created_uid');
        });
    }

    public function down(): void
    {
        Schema::table('tickets_log', function (Blueprint $table) {
            $table->dropIndex('idx_tickets_log_ticket_id');
            $table->dropIndex('idx_tickets_log_created_uid');

            $table->dropColumn(['action', 'meta']);
        });
    }
};

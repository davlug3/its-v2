<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('source', 20)->default('web')->after('approved_by_supervisor');
            $table->string('email_message_id', 255)->nullable()->after('source');
            $table->unsignedInteger('units_count')->default(0)->after('email_message_id');
            $table->timestamp('updated_at')->nullable()->after('units_count');

            $table->index('created_uid', 'idx_tickets_created_uid');
            $table->index('category_id', 'idx_tickets_category_id');
            $table->index('location_id', 'idx_tickets_location_id');
            $table->index('state_id', 'idx_tickets_state_id');
            $table->index('assigned_uid', 'idx_tickets_assigned_uid');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('idx_tickets_created_uid');
            $table->dropIndex('idx_tickets_category_id');
            $table->dropIndex('idx_tickets_location_id');
            $table->dropIndex('idx_tickets_state_id');
            $table->dropIndex('idx_tickets_assigned_uid');

            $table->dropColumn(['source', 'email_message_id', 'units_count', 'updated_at']);
        });
    }
};

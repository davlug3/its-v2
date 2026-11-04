<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachements', function (Blueprint $table) {
            $table->string('storage_path', 500)->nullable()->after('ticket_id');
            $table->string('mime_type', 120)->nullable()->after('storage_path');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->string('sha256', 64)->nullable()->after('size_bytes');
            $table->unsignedInteger('uploaded_by')->nullable()->after('sha256');
            $table->timestamp('created_at')->nullable()->after('uploaded_by');

            $table->index('ticket_id', 'idx_attachements_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('attachements', function (Blueprint $table) {
            $table->dropIndex('idx_attachements_ticket_id');

            $table->dropColumn(['storage_path', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by', 'created_at']);
        });
    }
};

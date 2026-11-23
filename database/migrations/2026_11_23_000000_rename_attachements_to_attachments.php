<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the misspelled legacy table to the correct name.
     */
    public function up(): void
    {
        if (Schema::hasTable('attachements')) {
            Schema::rename('attachements', 'attachments');
        }
    }

    /**
     * Revert the rename.
     */
    public function down(): void
    {
        if (Schema::hasTable('attachments')) {
            Schema::rename('attachments', 'attachements');
        }
    }
};

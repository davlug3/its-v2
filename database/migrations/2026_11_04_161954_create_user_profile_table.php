<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profile', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->unsignedTinyInteger('acl_id')->default(1);
            $table->unsignedInteger('group_id')->default(1);
            $table->unsignedTinyInteger('fpc')->default(1);
            $table->unsignedInteger('login_attempts')->default(0);
            $table->unsignedInteger('last_login_ip')->default(0);
            $table->unsignedInteger('last_login_dt')->default(0);
            $table->unsignedInteger('supervisor_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile');
    }
};

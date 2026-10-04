<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->dropUnique('users_email_unique');
            $table->dropColumn(['phone', 'email', 'email_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('name');
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });
    }
};
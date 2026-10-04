<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE import_logs MODIFY period_id BIGINT UNSIGNED NULL');

        Schema::table('import_logs', function (Blueprint $table) {
            $table->string('sheet_name', 100)->nullable()->after('file_name');
            $table->unsignedInteger('created_users')->default(0)->after('success_rows');
            $table->unsignedInteger('existing_users')->default(0)->after('created_users');
            $table->unsignedInteger('invalid_rows')->default(0)->after('failed_rows');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn(['sheet_name', 'created_users', 'existing_users', 'invalid_rows']);
        });

        DB::statement('ALTER TABLE import_logs MODIFY period_id BIGINT UNSIGNED NOT NULL');
    }
};
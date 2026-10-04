<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('wilayah_id')->nullable()->after('nama')->constrained('wilayah')->nullOnDelete();
            $table->string('address', 255)->nullable()->after('wilayah_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilayah_id');
            $table->dropColumn('address');
        });
    }
};
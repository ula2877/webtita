<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('arrears', function (Blueprint $table) {
            $table->string('foto_bukti', 500)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arrears', function (Blueprint $table) {
            $table->dropColumn('foto_bukti');
        });
    }
};

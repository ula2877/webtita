<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arrears', function (Blueprint $table) {
            $table->unsignedBigInteger('jumlah_tagihan')->nullable()->after('jumlah_bulan_tunggakan');
        });
    }

    public function down(): void
    {
        Schema::table('arrears', function (Blueprint $table) {
            $table->dropColumn('jumlah_tagihan');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jumlah_bulan_tunggakan');
            $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['belum_dikunjungi', 'sudah_dikunjungi'])->default('belum_dikunjungi');
            $table->unique(['customer_id', 'period_id']);
            $table->index(['period_id', 'petugas_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrears');
    }
};

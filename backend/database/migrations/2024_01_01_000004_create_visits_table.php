<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arrears_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('petugas_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status_kunjungan', ['ada_orang', 'rumah_kosong', 'tidak_ada_orang', 'lainnya']);
            $table->text('keterangan')->nullable();
            $table->string('foto_bukti', 255)->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};

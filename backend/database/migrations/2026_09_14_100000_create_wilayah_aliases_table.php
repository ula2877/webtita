<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $table->string('alias', 190)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah_aliases');
    }
};
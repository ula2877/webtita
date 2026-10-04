<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->string('label', 50);
            $table->unique(['year', 'month']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};

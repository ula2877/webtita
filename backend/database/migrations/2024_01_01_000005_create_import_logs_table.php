<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->enum('status', ['processing', 'success', 'failed', 'partial'])->default('processing');
            $table->json('error_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};

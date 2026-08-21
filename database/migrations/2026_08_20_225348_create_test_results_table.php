<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_execution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_id')->constrained()->cascadeOnDelete();
            $table->boolean('passed');
            $table->text('actual_output')->nullable();
            $table->text('actual_error')->nullable();
            $table->unsignedInteger('execution_duration_ms')->nullable();
            $table->enum('status', ['success', 'compile_error', 'runtime_error', 'timeout', 'memory_limit', 'resource_limit', 'system_error']);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
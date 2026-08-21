<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programming_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('language_execution_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained('activity_submissions')->nullOnDelete();
            $table->enum('status', ['queued', 'running', 'success', 'compile_error', 'runtime_error', 'timeout', 'memory_limit', 'resource_limit', 'cancelled', 'system_error'])->default('queued');
            $table->text('source_code');
            $table->text('stdout')->nullable();
            $table->text('stderr')->nullable();
            $table->text('compile_error')->nullable();
            $table->text('runtime_error')->nullable();
            $table->boolean('timeout')->default(false);
            $table->unsignedInteger('exit_code')->nullable();
            $table->unsignedInteger('execution_duration_ms')->nullable();
            $table->unsignedInteger('memory_used_kb')->nullable();
            $table->json('resource_usage')->nullable();
            $table->json('test_summary')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'activity_id']);
            $table->index(['submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_executions');
    }
};
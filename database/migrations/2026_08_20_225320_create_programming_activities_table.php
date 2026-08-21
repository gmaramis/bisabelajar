<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programming_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_execution_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->text('starter_code')->nullable();
            $table->json('editable_files')->nullable(); // files student can edit
            $table->unsignedInteger('execution_time_limit_seconds')->default(30);
            $table->unsignedInteger('memory_limit_mb')->default(256);
            $table->unsignedInteger('source_code_size_limit_kb')->default(64);
            $table->json('submission_rules')->nullable(); // e.g., max_submissions, allowed_languages
            $table->json('evaluation_config')->nullable(); // grading config
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programming_activities');
    }
};
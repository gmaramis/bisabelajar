<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('language_execution_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique(); // e.g., 'python', 'c', 'cpp', 'java', 'javascript', 'php', 'go', 'kotlin'
            $table->string('display_name'); // e.g., 'Python 3.11'
            $table->string('file_extension'); // e.g., '.py'
            $table->string('source_filename'); // e.g., 'main.py'
            $table->string('docker_image'); // e.g., 'python:3.11-slim'
            $table->text('compile_command')->nullable(); // e.g., 'gcc -o main main.c'
            $table->text('run_command'); // e.g., 'python main.py' or './main'
            $table->string('execution_mode')->default('interpreted'); // 'compiled' or 'interpreted'
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->unsignedInteger('memory_limit_mb')->default(256);
            $table->unsignedInteger('cpu_limit')->default(1); // CPU cores
            $table->boolean('network_enabled')->default(false);
            $table->boolean('enabled')->default(true);
            $table->json('environment_variables')->nullable();
            $table->json('allowed_files')->nullable(); // additional files allowed besides main
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('language_execution_profiles');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programming_activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('input')->nullable();
            $table->text('expected_output');
            $table->string('comparison_strategy')->default('exact'); // exact, regex, contains, custom
            $table->boolean('visible')->default(true); // visible to students or hidden
            $table->unsignedInteger('timeout_seconds')->nullable(); // per-test override
            $table->unsignedInteger('memory_limit_mb')->nullable(); // per-test override
            $table->json('metadata')->nullable(); // additional test config
            $table->timestamps();

            $table->index(['programming_activity_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
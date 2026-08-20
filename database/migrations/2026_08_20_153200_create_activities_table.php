<?php

use App\Enums\ActivityStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 32);
            $table->string('status', 32)->default(ActivityStatus::Draft->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->index(['learning_unit_id', 'sort_order']);
            $table->index(['learning_unit_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptive_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_state_id')->constrained('learning_states')->cascadeOnDelete();
            $table->string('intervention_key', 64);
            $table->string('intervention_type', 32);
            $table->string('socratic_type', 32)->nullable();
            $table->string('target_state', 32);
            $table->text('content');
            $table->text('reason');
            $table->string('selection_rule', 64);
            $table->boolean('is_strong')->default(true);
            $table->boolean('is_remedial')->default(false);
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique('intervention_key', 'adaptive_interventions_key_unique');
            $table->index(['user_id', 'activity_id'], 'adaptive_interventions_learner_activity');
            $table->index(['learning_state_id'], 'adaptive_interventions_learning_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptive_interventions');
    }
};

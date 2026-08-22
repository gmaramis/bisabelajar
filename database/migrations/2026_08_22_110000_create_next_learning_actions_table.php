<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_learning_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_state_id')->constrained('learning_states')->cascadeOnDelete();
            $table->foreignId('adaptive_intervention_id')->nullable()->constrained('adaptive_interventions')->nullOnDelete();
            $table->string('decision_key', 64);
            $table->string('action', 32);
            $table->text('reason');
            $table->string('decision_rule', 96);
            $table->string('retry_outcome', 16)->nullable();
            $table->json('metadata');
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique('decision_key', 'next_learning_actions_decision_key_unique');
            $table->index(['user_id', 'activity_id'], 'next_learning_actions_learner_activity');
            $table->index(['learning_state_id'], 'next_learning_actions_learning_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_learning_actions');
    }
};

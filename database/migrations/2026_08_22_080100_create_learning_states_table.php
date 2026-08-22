<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('inference_key', 64);
            $table->string('state', 32);
            $table->string('state_confidence', 16);
            $table->string('bloom_demand', 32)->nullable();
            $table->string('dave_demand', 32)->nullable();
            $table->string('cognitive_indicator', 64)->nullable();
            $table->string('psychomotor_indicator', 64)->nullable();
            $table->json('behavioral_indicators');
            $table->json('fusion_summary');
            $table->text('explanation');
            $table->string('inference_rule', 64);
            $table->timestamp('inferred_at');
            $table->timestamps();

            $table->unique('inference_key', 'learning_states_inference_key_unique');
            $table->index(['user_id', 'activity_id'], 'learning_states_learner_activity');
        });

        Schema::create('learning_state_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_state_id')->constrained('learning_states')->cascadeOnDelete();
            $table->foreignId('validated_evidence_id')->constrained('validated_evidence')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['learning_state_id', 'validated_evidence_id'],
                'learning_state_evidence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_state_evidence');
        Schema::dropIfExists('learning_states');
    }
};

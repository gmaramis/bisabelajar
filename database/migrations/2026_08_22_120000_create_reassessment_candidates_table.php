<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist AI-assisted reassessment candidates and provenance (M5-04).
 *
 * Justified because generated content, validation status, generator metadata,
 * and weak-area lineage are not represented by NextLearningAction (decision only)
 * and no question-bank table exists. This does not deliver tasks to learners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reassessment_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('candidate_key', 64);
            $table->string('research_learner_id', 64);
            $table->string('learning_area_key', 191);
            $table->string('learning_area_label', 191)->nullable();
            $table->string('learning_area_representation', 64);
            $table->string('weak_area_classification', 64);
            $table->string('concept', 191)->nullable();
            $table->text('learning_objective')->nullable();
            $table->string('bloom_demand', 32)->nullable();
            $table->string('dave_demand', 32)->nullable();
            $table->string('status', 64);
            $table->json('specification');
            $table->json('ai_safe_payload')->nullable();
            $table->json('candidate_content')->nullable();
            $table->string('generator_identity', 128)->nullable();
            $table->string('generator_model', 128)->nullable();
            $table->json('generation_metadata')->nullable();
            $table->json('validation_result')->nullable();
            $table->json('validation_errors')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique('candidate_key', 'reassessment_candidates_candidate_key_unique');
            $table->index(['user_id', 'course_id'], 'reassessment_candidates_learner_course');
            $table->index(['learning_area_key'], 'reassessment_candidates_learning_area');
            $table->index(['status'], 'reassessment_candidates_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reassessment_candidates');
    }
};

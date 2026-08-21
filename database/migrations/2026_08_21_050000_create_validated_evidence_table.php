<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validated_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('learning_event_id')->constrained()->cascadeOnDelete();
            $table->string('source_record_type', 64)->nullable();
            $table->unsignedBigInteger('source_record_id')->nullable();
            $table->string('evidence_category', 32);
            $table->string('evidence_type', 64);
            $table->json('observed_value');
            $table->json('context_summary');
            $table->string('quality', 32);
            $table->string('confidence', 32);
            $table->text('validation_reason');
            $table->timestamp('validated_at');
            $table->timestamps();

            $table->index(['user_id', 'activity_id'], 'validated_evidence_learner_activity');
            $table->unique(
                ['learning_event_id', 'evidence_category', 'evidence_type'],
                'validated_evidence_event_kind'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validated_evidence');
    }
};

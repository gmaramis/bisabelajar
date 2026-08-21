<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 64); // e.g., PROGRAMMING_ACTIVITY_OPENED, CODE_EDITED, CODE_RUN, etc.
            $table->json('payload')->nullable(); // event-specific data
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('session_id')->nullable(); // for grouping related events

            $table->index(['user_id', 'event_type']);
            $table->index(['activity_id', 'event_type']);
            $table->index(['occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_events');
    }
};
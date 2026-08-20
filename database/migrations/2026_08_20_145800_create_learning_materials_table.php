<?php

use App\Enums\MaterialStatus;
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
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 32);
            $table->longText('content')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->string('file_path')->nullable();
            $table->string('disk', 32)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default(MaterialStatus::Draft->value);
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
        Schema::dropIfExists('learning_materials');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visual_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('game_sessions')->nullOnDelete();
            $table->enum('status', ['normal', 'visual_disorder']);
            $table->string('label');
            $table->decimal('confidence', 6, 4);
            $table->decimal('prob_normal', 6, 4);
            $table->decimal('prob_disorder', 6, 4);
            $table->json('weak_skills')->nullable();
            $table->json('training_plan')->nullable();
            $table->unsignedSmallInteger('trials_count')->default(0);
            $table->string('model_version')->default('v1.0.0');
            $table->timestamps();

            $table->index(['child_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visual_predictions');
    }
};

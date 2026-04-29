<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->enum('difficulty_level', ['Easy', 'Medium', 'Hard'])->default('Easy');
            $table->unsignedSmallInteger('level')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_sec')->default(0);
            $table->unsignedSmallInteger('trials_count')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('errors_count')->default(0);
            $table->unsignedSmallInteger('missed_count')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);
            $table->decimal('avg_reaction_time_ms', 10, 2)->default(0);
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            $table->timestamps();

            $table->index(['child_id', 'status']);
            $table->index(['child_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};

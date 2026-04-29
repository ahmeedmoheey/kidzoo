<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('trial_number');
            $table->string('task_type')->comment('Tracking|Discrimination|Matching|Orientation');
            $table->string('difficulty_level')->comment('Easy|Medium|Hard');
            $table->string('target_type')->comment('Direction|Color|Shape|Position');
            $table->unsignedSmallInteger('stimulus_count');
            $table->unsignedInteger('reaction_time_ms');
            $table->boolean('correct')->default(false);
            $table->unsignedSmallInteger('errors')->default(0);
            $table->unsignedSmallInteger('missed_targets')->default(0);
            $table->unsignedInteger('duration_sec')->default(0);
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_trials');
    }
};

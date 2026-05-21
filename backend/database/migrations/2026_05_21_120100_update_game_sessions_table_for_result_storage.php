<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('game_sessions', 'score')) {
                $table->unsignedSmallInteger('score')->default(0)->after('avg_reaction_time_ms');
            }

            if (! Schema::hasColumn('game_sessions', 'max_score')) {
                $table->unsignedSmallInteger('max_score')->default(0)->after('score');
            }

            if (! Schema::hasColumn('game_sessions', 'stars')) {
                $table->unsignedTinyInteger('stars')->default(0)->after('max_score');
            }

            if (! Schema::hasColumn('game_sessions', 'result_payload')) {
                $table->json('result_payload')->nullable()->after('stars');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('game_sessions', 'score') ? 'score' : null,
                Schema::hasColumn('game_sessions', 'max_score') ? 'max_score' : null,
                Schema::hasColumn('game_sessions', 'stars') ? 'stars' : null,
                Schema::hasColumn('game_sessions', 'result_payload') ? 'result_payload' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

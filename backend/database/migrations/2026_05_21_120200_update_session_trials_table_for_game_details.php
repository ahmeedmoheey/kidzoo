<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_trials', function (Blueprint $table) {
            if (! Schema::hasColumn('session_trials', 'prompt_value')) {
                $table->string('prompt_value')->nullable()->after('target_type');
            }

            if (! Schema::hasColumn('session_trials', 'selected_value')) {
                $table->string('selected_value')->nullable()->after('prompt_value');
            }

            if (! Schema::hasColumn('session_trials', 'metadata')) {
                $table->json('metadata')->nullable()->after('duration_sec');
            }
        });
    }

    public function down(): void
    {
        Schema::table('session_trials', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('session_trials', 'prompt_value') ? 'prompt_value' : null,
                Schema::hasColumn('session_trials', 'selected_value') ? 'selected_value' : null,
                Schema::hasColumn('session_trials', 'metadata') ? 'metadata' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

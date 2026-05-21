<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            if (! Schema::hasColumn('games', 'target_type')) {
                $table->string('target_type')->default('Shape')->after('task_type');
            }

            if (! Schema::hasColumn('games', 'asset_type')) {
                $table->string('asset_type')->default('mixed')->after('icon_url');
            }

            if (! Schema::hasColumn('games', 'settings')) {
                $table->json('settings')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('games', 'target_type') ? 'target_type' : null,
                Schema::hasColumn('games', 'asset_type') ? 'asset_type' : null,
                Schema::hasColumn('games', 'settings') ? 'settings' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

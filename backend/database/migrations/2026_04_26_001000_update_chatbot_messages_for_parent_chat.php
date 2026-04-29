<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('child_id')->nullable()->change();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropColumn('user_id');
            $table->unsignedBigInteger('child_id')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->enum('role', ['user', 'bot']);
            $table->text('message');
            $table->timestamps();

            $table->index(['child_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
    }
};

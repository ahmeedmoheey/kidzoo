<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('age');
            $table->enum('gender', ['boy', 'girl']);
            $table->string('avatar_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};

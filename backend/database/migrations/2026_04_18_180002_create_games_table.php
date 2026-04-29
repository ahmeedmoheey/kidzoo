<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('task_type')->comment('ML task mapping: Tracking|Discrimination|Matching|Orientation');
            $table->string('skill')->comment('Visual Tracking|Visual Discrimination|Visual Matching|Spatial Orientation');
            $table->unsignedSmallInteger('min_age')->default(3);
            $table->unsignedSmallInteger('max_age')->default(12);
            $table->unsignedTinyInteger('total_levels')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

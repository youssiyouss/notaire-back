<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('word_transformations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('template_groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('placeholder');
            $table->string('masculine');
            $table->string('feminine');
            $table->string('masculine_plural');
            $table->string('feminine_plural');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_transformations');
    }
};

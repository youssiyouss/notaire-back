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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('contract_templates')->cascadeOnDelete();
            $table->unsignedBigInteger('notaire_id')->nullable();
            $table->double('price')->nullable();
            $table->string('status');
            $table->string('pdf_path')->nullable();
            $table->string('word_path')->nullable();
            $table->string('receiptPath')->nullable();
            $table->foreign('notaire_id')->references('id')->on('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

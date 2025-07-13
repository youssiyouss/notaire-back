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
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_type_id')->constrained('contract_types')->onDelete('cascade');
            $table->string('contract_subtype');
            $table->longText('content');
            $table->string('taxe_type');
            $table->decimal('taxe_pourcentage', 10, 2);

            $table->decimal('original', 10, 2)->nullable();
            $table->decimal('copy', 10, 2)->nullable();
            $table->decimal('documentation', 10, 2)->nullable();
            $table->decimal('publication', 10, 2)->nullable();
            $table->decimal('consultation', 10, 2)->nullable();
            $table->decimal('consultationFee', 10, 2)->nullable();
            $table->decimal('workFee', 10, 2)->nullable();
            $table->decimal('others', 10, 2)->nullable();
            $table->decimal('stamp', 10, 2)->nullable();
            $table->decimal('registration', 10, 2)->nullable();
            $table->decimal('advertisement', 10, 2)->nullable();
            $table->decimal('rkm', 10, 2)->nullable();
            $table->decimal('announcements', 10, 2)->nullable();
            $table->decimal('deposit', 10, 2)->nullable();
            $table->decimal('boal', 10, 2)->nullable();
            $table->decimal('registration_or_cancellation', 10, 2)->nullable();

            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};

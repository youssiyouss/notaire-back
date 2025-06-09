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
        Schema::table('contract_templates', function (Blueprint $table) {
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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn([
                'original',
                'copy',
                'documentation',
                'publication',
                'consultation',
                'consultationFee',
                'workFee',
                'others',
                'stamp',
                'registration',
                'advertisement',
                'rkm',
                'announcements',
                'deposit',
                'boal',
                'registration_or_cancellation',
            ]);
        });
    }
};

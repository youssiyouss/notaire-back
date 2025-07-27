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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('logo')->nullable();
            $table->string('nom_commercial'); // Exemple : BNA
            $table->string('forme_juridique')->nullable(); // SPA, SARL
            $table->string('capital_social')->nullable(); // 150.000.000.000 DZD
            $table->string('adresse_siege')->nullable();
            $table->string('registre_commerce')->nullable();
            $table->date('date_rc')->nullable();
            $table->date('date_creation')->nullable();
            $table->string('wilaya_rc')->nullable();
            $table->string('nif')->nullable(); // Numéro d’identification fiscale
            $table->string('nis')->nullable();
            $table->string('ai')->nullable();
            $table->string('boal')->nullable();
            $table->string('activite_principale')->nullable(); // 612103 Banque
            $table->foreignId('owner')->constrained('users')->onDelete('cascade');
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
        Schema::dropIfExists('companies');
    }
};

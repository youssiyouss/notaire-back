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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('nationalite')->nullable();
            $table->string('lieu_de_naissance')->nullable();
            $table->string('nom_maternelle')->nullable();
            $table->string('prenom_mere')->nullable();
            $table->string('prenom_pere')->nullable();
            $table->string('numero_acte_naissance')->nullable();
            $table->string('type_carte')->default('identite');
            $table->string('date_emission_carte')->nullable();
            $table->string('lieu_emission_carte')->nullable();
            $table->string('emploi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

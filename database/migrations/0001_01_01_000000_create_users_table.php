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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('tel')->unique(); // Ensure phone is unique
            $table->string('adresse')->nullable();
            $table->string('nationalite')->nullable();
            $table->boolean('sexe')->nullable();
            $table->boolean('date_de_naissance')->nullable();
            $table->boolean('lieu_de_naissance')->nullable();
            $table->string('nom_maternelle')->nullable();
            $table->string('prenom_mere')->nullable();
            $table->string('prenom_pere')->nullable();
            $table->string('numero_acte_naissance')->nullable();
            $table->string('role')->default('client');
            $table->string('type_carte')->default('identite');
            $table->string('date_emission_carte')->nullable();
            $table->string('lieu_emission_carte')->nullable();
            $table->string('emploi')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('picture')->nullable();
            $table->rememberToken();
            $table->timestamps();


        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

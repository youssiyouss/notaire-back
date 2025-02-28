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
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('tel')->unique();
            $table->string('adresse')->nullable();
            $table->enum('sexe', ['male', 'female'])->nullable();
            $table->date('date_de_naissance')->nullable();
            $table->string('role')->default('client');
            $table->string('password');
            $table->string('picture')->nullable();
            $table->string('ccp')->nullable();
            $table->decimal('salaire', 10, 2)->nullable();
            $table->string('date_virement_salaire')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email'); // Store email for verification
            $table->string('token');
            $table->string('type')->nullable();
            $table->timestamp('expires_at')->nullable(); // Optional expiration
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

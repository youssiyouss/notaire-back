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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // employee who asks for leave
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('type', ['annual', 'sick', 'maternity', 'paternity', 'unpaid', 'other'])->default('annual');
            $table->text('comment')->nullable(); // employee's comment
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->foreignId('responded_by')->nullable()->constrained('users')->onDelete('set null'); // admin
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};

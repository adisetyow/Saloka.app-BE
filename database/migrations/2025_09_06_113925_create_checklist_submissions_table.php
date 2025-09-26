<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checklist_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_schedule_id')->constrained('checklist_schedules')->onDelete('cascade');
            $table->date('submission_date')->index();
            $table->foreignId('submitted_by')->constrained('users')->onDelete('restrict');
            $table->enum('status', ['completed', 'incomplete', 'pending'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_submissions');
    }
};
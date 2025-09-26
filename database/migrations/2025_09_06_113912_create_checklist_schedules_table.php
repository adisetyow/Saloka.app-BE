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
        Schema::create('checklist_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_master_id')->constrained('checklist_masters')->onDelete('cascade');
            $table->string('schedule_name');
            $table->enum('periode_type', ['harian', 'mingguan', 'bulanan', 'tertentu']);
            $table->json('schedule_details')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_schedules');
    }
};
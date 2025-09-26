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
        Schema::create('checklist_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_master_id')->constrained('checklist_masters')->onDelete('cascade');
            $table->string('user_id');
            $table->string('name');
            $table->string('activity');
            $table->text('detail_act');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_logs');
    }
};
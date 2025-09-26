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
        Schema::create('checklist_submission_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('checklist_submissions')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('checklist_items')->onDelete('cascade');
            $table->boolean('is_checked')->default(false);
            $table->string('notes')->nullable();

            $table->unique(['submission_id', 'item_id'], 'unique_submission_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_submission_details');
    }
};
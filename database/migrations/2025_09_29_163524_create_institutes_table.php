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
        Schema::create('institutes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();        // Short name / code
            $table->string('full_name');             // Full official name
            $table->text('description')->nullable(); // Optional description
            $table->text('analysis')->nullable(); // Optional description
            $table->timestamps();
        });

        // Add institute_id column to professors table
        Schema::table('professors', function (Blueprint $table) {
            $table->foreignId('institute_id')
                  ->nullable()
                  ->constrained('institutes')
                  ->nullOnDelete(); // if institute deleted, set null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professors', function (Blueprint $table) {
            $table->dropForeign(['institute_id']);
            $table->dropColumn('institute_id');
        });

        Schema::dropIfExists('institutes');
    }
};

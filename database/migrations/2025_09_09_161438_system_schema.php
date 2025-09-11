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
        // Admins table
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // Professors table
        Schema::create('professors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });

        // Classrooms table
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('prof_id')->constrained('professors')->onDelete('cascade');
            $table->string('subject');
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->string('code')->unique();
            $table->enum('sentiment_analysis', ['Positive', 'Neutral', 'Negative'])->nullable();
            $table->text('ai_analysis')->nullable();
            $table->text('ai_recommendation')->nullable();
            $table->timestamps();
        });

        // Classroom students table
        Schema::create('classroom_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade'); // FK to users
            $table->timestamps();
        });

        // Evaluations table
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_student_id')->constrained('classroom_students')->onDelete('cascade');
            $table->integer('rating')->nullable();        // 1–5 stars
            $table->text('comment')->nullable();          // student feedback
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('classroom_students');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('professors');
        Schema::dropIfExists('admins');
    }
};

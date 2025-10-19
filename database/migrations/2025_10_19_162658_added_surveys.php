<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // --- SURVEYS ---
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->nullable()->default('pending'); // flexible
            $table->timestamps();
        });

        // --- SURVEY SECTIONS ---
        Schema::create('survey_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
        });

        // --- SURVEY QUESTIONS ---
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->text('question_text');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('section_id')->references('id')->on('survey_sections')->onDelete('cascade');
        });

        // --- SURVEY RESPONSES ---
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('survey_question_id');
            $table->integer('rating')->nullable();
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('survey_question_id')->references('id')->on('survey_questions')->onDelete('cascade');
        });

        // --- UPDATE CLASSROOMS ---
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('survey_id')->nullable()->after('prof_id');
            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['survey_id']);
            $table->dropColumn('survey_id');
        });

        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('survey_sections');
        Schema::dropIfExists('surveys');
    }
};

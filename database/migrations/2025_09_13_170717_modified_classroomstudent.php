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
        Schema::table('classroom_students', function (Blueprint $table) {
            $table->integer('rating')->nullable()->after('student_id');
            $table->text('comment')->nullable()->after('rating');
            $table->string('sentiment')->nullable()->after('comment');
            $table->decimal('sentiment_score', 5, 2)->nullable()->after('sentiment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classroom_students', function (Blueprint $table) {
            $table->dropColumn(['rating', 'comment', 'sentiment', 'sentiment_score']);
        });
    }
};

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
        Schema::table('evaluations', function (Blueprint $table) {
            $table->string('sentiment')->nullable()->after('comment'); // flexible, not limited
            $table->decimal('sentiment_score', 5, 2)->nullable()->after('sentiment'); // confidence level 0.00–1.00
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['sentiment', 'sentiment_score']);
        });
    }
};

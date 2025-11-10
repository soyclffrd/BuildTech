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
        Schema::table('quizzes', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('quizzes', 'course_id')) {
                $table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('quizzes', 'trainer_id')) {
                $table->foreignId('trainer_id')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('quizzes', 'title')) {
                $table->string('title')->nullable();
            }
            if (!Schema::hasColumn('quizzes', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('quizzes', 'time_limit')) {
                $table->integer('time_limit')->nullable();
            }
            if (!Schema::hasColumn('quizzes', 'passing_score')) {
                $table->integer('passing_score')->default(70);
            }
            if (!Schema::hasColumn('quizzes', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('quizzes', 'course_id')) {
                $table->dropForeign(['course_id']);
                $table->dropColumn('course_id');
            }
            if (Schema::hasColumn('quizzes', 'trainer_id')) {
                $table->dropForeign(['trainer_id']);
                $table->dropColumn('trainer_id');
            }
            if (Schema::hasColumn('quizzes', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('quizzes', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('quizzes', 'time_limit')) {
                $table->dropColumn('time_limit');
            }
            if (Schema::hasColumn('quizzes', 'passing_score')) {
                $table->dropColumn('passing_score');
            }
            if (Schema::hasColumn('quizzes', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};

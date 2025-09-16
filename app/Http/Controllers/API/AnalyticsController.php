<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\ClassroomStudent;

class AnalyticsController extends Controller
{
    public function index()
    {
        // 1. Total active classrooms
        $totalClassrooms = Classroom::where('status', 'active')->count();

        // 2. Total students in active classrooms
        $totalClassroomStudents = ClassroomStudent::whereHas('classroom', function ($query) {
            $query->where('status', 'active');
        })->count();

        // 3. Students with both rating & comment in active classrooms
        $studentsWithFeedback = ClassroomStudent::whereHas('classroom', function ($query) {
            $query->where('status', 'active');
        })
            ->whereNotNull('rating')
            ->whereNotNull('comment')
            ->count();

        // 4. Average rating in active classrooms
        $averageRating = ClassroomStudent::whereHas('classroom', function ($query) {
            $query->where('status', 'active');
        })->avg('rating');

        // 5. Sentiment counts in active classrooms
        $sentimentCounts = ClassroomStudent::whereHas('classroom', function ($query) {
            $query->where('status', 'active');
        })
            ->selectRaw('sentiment, COUNT(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        $positiveCount = $sentimentCounts['Positive'] ?? 0;
        $negativeCount = $sentimentCounts['Negative'] ?? 0;
        $neutralCount  = $sentimentCounts['Neutral'] ?? 0;

        $totalSentiments = $positiveCount + $negativeCount + $neutralCount;

        $positivePercentage = $totalSentiments > 0 
            ? round(($positiveCount / $totalSentiments) * 100, 2) 
            : 0;

        return response()->json([
            'total_classrooms' => $totalClassrooms,
            'total_classroom_students' => $totalClassroomStudents,
            'students_with_feedback' => $studentsWithFeedback,
            'average_rating' => round($averageRating, 2),
            'sentiment_distribution' => [
                'positive' => $positiveCount,
                'negative' => $negativeCount,
                'neutral'  => $neutralCount,
            ],
            'positive_percentage' => $positivePercentage,
        ]);
    }
}

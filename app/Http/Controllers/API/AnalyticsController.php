<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\ClassroomStudent;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // Get professor ID from authenticated user
        $professorId = auth('professor-api')->id();

        // 1. Total active classrooms for this professor
        $totalClassrooms = Classroom::where('prof_id', $professorId)
            ->where('status', 'active')
            ->count();

        // 2. Total students in professor's active classrooms
        $totalClassroomStudents = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })->count();

        // 3. Students with both rating & comment in professor's active classrooms
        $studentsWithFeedback = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })
            ->whereNotNull('rating')
            ->whereNotNull('comment')
            ->count();

        // 4. Average rating in professor's active classrooms
        $averageRating = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })->avg('rating');

        // 5. Sentiment counts in professor's active classrooms
        $sentimentCounts = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })
            ->selectRaw('sentiment, COUNT(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        $positiveCount = $sentimentCounts['positive'] ?? 0;
        $negativeCount = $sentimentCounts['negative'] ?? 0;
        $neutralCount  = $sentimentCounts['neutral'] ?? 0;

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

    public function getClassroomPerformance(Request $request)
    {
        $professorId = auth('professor-api')->id();

        $classrooms = Classroom::where('prof_id', $professorId)
            ->where('status', 'active')
            ->withCount('students as student_count')
            ->with(['students' => function ($query) {
                $query->select('classroom_id', 'rating', 'sentiment')
                    ->whereNotNull('rating');
            }])
            ->get()
            ->map(function ($classroom) {
                $ratings = $classroom->students->pluck('rating')->filter();
                $sentiments = $classroom->students->pluck('sentiment');
                
                $positiveCount = $sentiments->filter(fn($s) => $s === 'positive')->count();
                $totalSentiments = $sentiments->count();
                
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'subject' => $classroom->subject,
                    'student_count' => $classroom->student_count,
                    'average_rating' => $ratings->count() > 0 ? round($ratings->average(), 2) : 0,
                    'total_ratings' => $ratings->count(),
                    'positive_sentiment_percentage' => $totalSentiments > 0 
                        ? round(($positiveCount / $totalSentiments) * 100, 2) 
                        : 0,
                ];
            });

        return response()->json($classrooms);
    }

    public function getRecentFeedback(Request $request)
    {
        $professorId = auth('professor-api')->id();
        $limit = $request->get('limit', 10);

        $recentFeedback = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })
            ->whereNotNull('comment')
            ->whereNotNull('rating')
            ->with(['classroom:id,name,subject', 'student:id,name'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($feedback) {
                return [
                    'id' => $feedback->id,
                    'classroom_name' => $feedback->classroom->name,
                    'classroom_subject' => $feedback->classroom->subject,
                    'student_name' => "Anonymous",
                    'rating' => $feedback->rating,
                    'comment' => $feedback->comment,
                    'sentiment' => $feedback->sentiment,
                    'sentiment_score' => $feedback->sentiment_score,
                    'date' => $feedback->updated_at->format('M j, Y'),
                    'time_ago' => $feedback->updated_at->diffForHumans(),
                ];
            });

        return response()->json($recentFeedback);
    }

    public function getTrendData(Request $request)
    {
        $professorId = auth('professor-api')->id();
        //$days = $request->get('days', 30);
        
        //$startDate = Carbon::now()->subDays($days);

        // Rating trends over time
        $ratingTrends = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })
            ->whereNotNull('rating')
            //->where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, AVG(rating) as avg_rating, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M j'),
                    'avg_rating' => round($item->avg_rating, 2),
                    'count' => $item->count,
                ];
            });

        // Sentiment trends over time
        $sentimentTrends = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })
            ->whereNotNull('sentiment')
            //->where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, sentiment, COUNT(*) as count')
            ->groupBy('date', 'sentiment')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($dayData, $date) {
                $total = $dayData->sum('count');
                $positive = $dayData->where('sentiment', 'positive')->first()->count ?? 0;
                $negative = $dayData->where('sentiment', 'negative')->first()->count ?? 0;
                $neutral = $dayData->where('sentiment', 'neutral')->first()->count ?? 0;

                return [
                    'date' => Carbon::parse($date)->format('M j'),
                    'positive_percentage' => $total > 0 ? round(($positive / $total) * 100, 1) : 0,
                    'negative_percentage' => $total > 0 ? round(($negative / $total) * 100, 1) : 0,
                    'neutral_percentage' => $total > 0 ? round(($neutral / $total) * 100, 1) : 0,
                    'total_feedback' => $total,
                ];
            })
            ->values();

        return response()->json([
            'rating_trends' => $ratingTrends,
            'sentiment_trends' => $sentimentTrends,
        ]);
    }

    public function getTopPerformingClassrooms(Request $request)
    {
        $professorId = auth('professor-api')->id();
        $limit = $request->get('limit', 5);

        $topClassrooms = Classroom::where('prof_id', $professorId)
            ->where('status', 'active')
            ->withCount('students as total_students')
            ->with(['students' => function ($query) {
                $query->whereNotNull('rating');
            }])
            ->get()
            ->map(function ($classroom) {
                $ratings = $classroom->students->pluck('rating');
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'subject' => $classroom->subject,
                    'total_students' => $classroom->total_students,
                    'rated_students' => $ratings->count(),
                    'average_rating' => $ratings->count() > 0 ? $ratings->average() : 0,
                ];
            })
            ->sortByDesc('average_rating')
            ->take($limit)
            ->values();

        return response()->json($topClassrooms);
    }

    public function getRatingDistribution(Request $request)
    {
        $professorId = auth('professor-api')->id();

        $distribution = ClassroomStudent::whereHas('classroom', function ($query) use ($professorId) {
            $query->where('prof_id', $professorId)->where('status', 'active');
        })
            ->whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->rating => $item->count];
            });

        // Ensure all ratings 1-5 are represented
        $ratingDistribution = collect(range(1, 5))->mapWithKeys(function ($rating) use ($distribution) {
            return [$rating => $distribution->get($rating, 0)];
        });

        return response()->json($ratingDistribution);
    }
}
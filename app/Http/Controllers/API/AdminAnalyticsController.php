<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Professor;
use App\Models\User;
use App\Models\Classroom;
use App\Models\ClassroomStudent;
use Carbon\Carbon;

class AdminAnalyticsController extends Controller
{
    /**
     * Get system overview statistics
     */
    public function systemOverview()
    {
        // Basic counts
        $totalProfessors = Professor::count();
        $totalStudents = User::count();
        $totalClassrooms = Classroom::count();
        $activeClassrooms = Classroom::where('status', 'active')->count();
        $inactiveClassrooms = Classroom::where('status', '!=', 'active')->count();

        // Feedback statistics
        $totalFeedback = ClassroomStudent::whereNotNull('rating')->whereNotNull('comment')->count();
        $averageSystemRating = ClassroomStudent::whereNotNull('rating')->avg('rating');

        // Sentiment distribution
        $sentimentCounts = ClassroomStudent::whereNotNull('sentiment')
            ->selectRaw('sentiment, COUNT(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        $positiveCount = $sentimentCounts['positive'] ?? 0;
        $negativeCount = $sentimentCounts['negative'] ?? 0;
        $neutralCount = $sentimentCounts['neutral'] ?? 0;
        $totalSentiments = $positiveCount + $negativeCount + $neutralCount;

        // Enrollment statistics
        $totalEnrollments = ClassroomStudent::count();
        $avgStudentsPerClassroom = $totalClassrooms > 0 ? round($totalEnrollments / $totalClassrooms, 2) : 0;

        return response()->json([
            'professors' => [
                'total' => $totalProfessors,
                'active_with_classrooms' => Professor::has('classrooms')->count(),
                'without_classrooms' => Professor::doesntHave('classrooms')->count(),
            ],
            'students' => [
                'total' => $totalStudents,
                'enrolled' => ClassroomStudent::distinct('student_id')->count('student_id'),
                'with_feedback' => ClassroomStudent::whereNotNull('rating')->whereNotNull('comment')->distinct('student_id')->count('student_id'),
            ],
            'classrooms' => [
                'total' => $totalClassrooms,
                'active' => $activeClassrooms,
                'inactive' => $inactiveClassrooms,
                'avg_students_per_classroom' => $avgStudentsPerClassroom,
            ],
            'feedback' => [
                'total_feedback' => $totalFeedback,
                'average_rating' => round($averageSystemRating, 2),
                'completion_rate' => $totalEnrollments > 0 ? round(($totalFeedback / $totalEnrollments) * 100, 2) : 0,
            ],
            'sentiment' => [
                'positive' => $positiveCount,
                'negative' => $negativeCount,
                'neutral' => $neutralCount,
                'positive_percentage' => $totalSentiments > 0 ? round(($positiveCount / $totalSentiments) * 100, 2) : 0,
            ],
        ]);
    }

    /**
     * Get professor performance analytics
     */
    public function professorAnalytics(Request $request)
    {
        $limit = $request->get('limit', 20);
        $sortBy = $request->get('sort_by', 'average_rating'); // average_rating, total_students, total_classrooms
        $sortOrder = $request->get('sort_order', 'desc');

        $professors = Professor::withCount([
                'classrooms as total_classrooms',
                'classrooms as active_classrooms' => function ($query) {
                    $query->where('status', 'active');
                }
            ])
            ->with(['classrooms' => function ($query) {
                $query->with(['students' => function ($studentQuery) {
                    $studentQuery->select('classroom_id', 'rating', 'sentiment');
                }]);
            }])
            ->get()
            ->map(function ($professor) {
                // Calculate aggregated statistics
                $allRatings = collect();
                $allSentiments = collect();
                $totalStudents = 0;

                foreach ($professor->classrooms as $classroom) {
                    $ratings = $classroom->students->pluck('rating')->filter();
                    $sentiments = $classroom->students->pluck('sentiment')->filter();
                    
                    $allRatings = $allRatings->merge($ratings);
                    $allSentiments = $allSentiments->merge($sentiments);
                    $totalStudents += $classroom->students->count();
                }

                $positiveCount = $allSentiments->filter(fn($s) => $s === 'positive')->count();
                $negativeCount = $allSentiments->filter(fn($s) => $s === 'negative')->count();
                $totalSentiments = $allSentiments->count();

                return [
                    'id' => $professor->id,
                    'name' => $professor->name,
                    'email' => $professor->email,
                    'total_classrooms' => $professor->total_classrooms,
                    'active_classrooms' => $professor->active_classrooms,
                    'total_students' => $totalStudents,
                    'total_ratings' => $allRatings->count(),
                    'average_rating' => $allRatings->count() > 0 ? round($allRatings->average(), 2) : 0,
                    'positive_sentiments' => $positiveCount,
                    'negative_sentiments' => $negativeCount,
                    'positive_percentage' => $totalSentiments > 0 ? round(($positiveCount / $totalSentiments) * 100, 2) : 0,
                    'feedback_completion_rate' => $totalStudents > 0 ? round(($allRatings->count() / $totalStudents) * 100, 2) : 0,
                ];
            });

        // Sort the collection
        if ($sortOrder === 'desc') {
            $professors = $professors->sortByDesc($sortBy);
        } else {
            $professors = $professors->sortBy($sortBy);
        }

        return response()->json($professors->take($limit)->values());
    }

    /**
     * Get classroom analytics across the system
     */
    public function classroomAnalytics(Request $request)
    {
        $limit = $request->get('limit', 50);
        $status = $request->get('status', null); // active, inactive, or null for all

        $query = Classroom::with(['professor:id,name,email'])
            ->withCount('students as total_students')
            ->with(['students' => function ($studentQuery) {
                $studentQuery->select('classroom_id', 'rating', 'sentiment')
                    ->whereNotNull('rating');
            }]);

        if ($status) {
            $query->where('status', $status);
        }

        $classrooms = $query->limit($limit)->get()->map(function ($classroom) {
            $ratings = $classroom->students->pluck('rating');
            $sentiments = $classroom->students->pluck('sentiment');
            
            $positiveCount = $sentiments->filter(fn($s) => $s === 'positive')->count();
            $totalSentiments = $sentiments->count();

            return [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'subject' => $classroom->subject,
                'status' => $classroom->status,
                'code' => $classroom->code,
                'professor' => [
                    'id' => $classroom->professor->id,
                    'name' => $classroom->professor->name,
                    'email' => $classroom->professor->email,
                ],
                'total_students' => $classroom->total_students,
                'students_with_ratings' => $ratings->count(),
                'average_rating' => $ratings->count() > 0 ? round($ratings->average(), 2) : 0,
                'positive_sentiment_percentage' => $totalSentiments > 0 ? round(($positiveCount / $totalSentiments) * 100, 2) : 0,
                'feedback_completion_rate' => $classroom->total_students > 0 ? round(($ratings->count() / $classroom->total_students) * 100, 2) : 0,
                'ai_analysis' => $classroom->ai_analysis,
                'ai_recommendation' => $classroom->ai_recommendation,
                'created_at' => $classroom->created_at->format('Y-m-d'),
            ];
        });

        return response()->json($classrooms);
    }

    /**
     * Get subject-wise performance analytics
     */
    public function subjectAnalytics()
    {
        $subjectData = Classroom::select('subject')
            ->withCount('students as total_enrollments')
            ->with(['students' => function ($query) {
                $query->select('classroom_id', 'rating', 'sentiment')
                    ->whereNotNull('rating');
            }])
            ->get()
            ->groupBy('subject')
            ->map(function ($classrooms, $subject) {
                $allRatings = collect();
                $allSentiments = collect();
                $totalEnrollments = 0;
                $totalClassrooms = $classrooms->count();

                foreach ($classrooms as $classroom) {
                    $ratings = $classroom->students->pluck('rating');
                    $sentiments = $classroom->students->pluck('sentiment');
                    
                    $allRatings = $allRatings->merge($ratings);
                    $allSentiments = $allSentiments->merge($sentiments);
                    $totalEnrollments += $classroom->total_enrollments;
                }

                $positiveCount = $allSentiments->filter(fn($s) => $s === 'positive')->count();
                $totalSentiments = $allSentiments->count();

                return [
                    'subject' => $subject,
                    'total_classrooms' => $totalClassrooms,
                    'total_enrollments' => $totalEnrollments,
                    'total_ratings' => $allRatings->count(),
                    'average_rating' => $allRatings->count() > 0 ? round($allRatings->average(), 2) : 0,
                    'positive_sentiment_percentage' => $totalSentiments > 0 ? round(($positiveCount / $totalSentiments) * 100, 2) : 0,
                    'feedback_completion_rate' => $totalEnrollments > 0 ? round(($allRatings->count() / $totalEnrollments) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('average_rating')
            ->values();

        return response()->json($subjectData);
    }

    /**
     * Get student engagement analytics
     */
    public function studentEngagement()
    {
        // Basic student statistics
        $totalStudents = User::count();
        $enrolledStudents = ClassroomStudent::distinct('student_id')->count('student_id');
        $activeStudents = ClassroomStudent::whereNotNull('rating')
            ->whereNotNull('comment')
            ->distinct('student_id')
            ->count('student_id');

        // Engagement metrics
        $avgClassroomsPerStudent = $enrolledStudents > 0 
            ? round(ClassroomStudent::count() / $enrolledStudents, 2) 
            : 0;

        // Rating distribution from all students
        $ratingDistribution = ClassroomStudent::whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating');

        // Top active students (most feedback given)
        $topActiveStudents = ClassroomStudent::select('student_id')
            ->whereNotNull('rating')
            ->whereNotNull('comment')
            ->with('student:id,name,email')
            ->selectRaw('student_id, COUNT(*) as feedback_count, AVG(rating) as avg_rating')
            ->groupBy('student_id')
            ->orderByDesc('feedback_count')
            ->limit(10)
            ->get()
            ->map(function ($record) {
                return [
                    'student_id' => $record->student_id,
                    'student_name' => $record->student->name,
                    'student_email' => $record->student->email,
                    'feedback_count' => $record->feedback_count,
                    'average_rating_given' => round($record->avg_rating, 2),
                ];
            });

        return response()->json([
            'overview' => [
                'total_students' => $totalStudents,
                'enrolled_students' => $enrolledStudents,
                'active_students' => $activeStudents,
                'engagement_rate' => $totalStudents > 0 ? round(($activeStudents / $totalStudents) * 100, 2) : 0,
                'avg_classrooms_per_student' => $avgClassroomsPerStudent,
            ],
            'rating_distribution' => $ratingDistribution,
            'top_active_students' => $topActiveStudents,
        ]);
    }

    /**
     * Get system trends over time
     */
    public function systemTrends(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);

        // Registration trends
        $professorTrends = Professor::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $classroomTrends = Classroom::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Feedback trends
        $feedbackTrends = ClassroomStudent::whereNotNull('rating')
            ->where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count, AVG(rating) as avg_rating')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Sentiment trends
        $sentimentTrends = ClassroomStudent::whereNotNull('sentiment')
            ->where('updated_at', '>=', $startDate)
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
                    'date' => $date,
                    'positive_count' => $positive,
                    'negative_count' => $negative,
                    'neutral_count' => $neutral,
                    'positive_percentage' => $total > 0 ? round(($positive / $total) * 100, 2) : 0,
                    'total_feedback' => $total,
                ];
            })
            ->values();

        return response()->json([
            'professor_registrations' => $professorTrends->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M j'),
                    'count' => $item->count,
                ];
            }),
            'classroom_creation' => $classroomTrends->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M j'),
                    'count' => $item->count,
                ];
            }),
            'feedback_trends' => $feedbackTrends->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M j'),
                    'count' => $item->count,
                    'avg_rating' => round($item->avg_rating, 2),
                ];
            }),
            'sentiment_trends' => $sentimentTrends,
        ]);
    }

    /**
     * Get content moderation data (flagged content, low ratings)
     */
    public function contentModeration(Request $request)
    {
        $limit = $request->get('limit', 20);

        // Low-rated classrooms that might need attention
        $lowRatedClassrooms = Classroom::with(['professor:id,name,email', 'students' => function ($query) {
                $query->select('classroom_id', 'rating', 'comment', 'sentiment')
                    ->whereNotNull('rating');
            }])
            ->get()
            ->map(function ($classroom) {
                $ratings = $classroom->students->pluck('rating');
                $averageRating = $ratings->count() > 0 ? $ratings->average() : 0;
                
                return [
                    'classroom' => $classroom,
                    'average_rating' => $averageRating,
                    'rating_count' => $ratings->count(),
                ];
            })
            ->filter(function ($item) {
                return $item['average_rating'] > 0 && $item['average_rating'] < 3.0 && $item['rating_count'] >= 3;
            })
            ->sortBy('average_rating')
            ->take($limit)
            ->map(function ($item) {
                return [
                    'classroom_id' => $item['classroom']->id,
                    'classroom_name' => $item['classroom']->name,
                    'subject' => $item['classroom']->subject,
                    'professor_name' => $item['classroom']->professor->name,
                    'professor_email' => $item['classroom']->professor->email,
                    'average_rating' => round($item['average_rating'], 2),
                    'total_ratings' => $item['rating_count'],
                    'status' => $item['classroom']->status,
                ];
            })
            ->values();

        // Recent negative feedback that might need review
        $negativeFeedback = ClassroomStudent::where('sentiment', 'negative')
            ->whereNotNull('comment')
            ->with(['classroom:id,name,subject', 'classroom.professor:id,name', 'student:id,name'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($feedback) {
                return [
                    'id' => $feedback->id,
                    'classroom_name' => $feedback->classroom->name,
                    'subject' => $feedback->classroom->subject,
                    'professor_name' => $feedback->classroom->professor->name,
                    'student_name' => $feedback->student->name,
                    'rating' => $feedback->rating,
                    'comment' => $feedback->comment,
                    'sentiment_score' => $feedback->sentiment_score,
                    'date' => $feedback->updated_at->format('M j, Y'),
                ];
            });

        return response()->json([
            'low_rated_classrooms' => $lowRatedClassrooms,
            'negative_feedback' => $negativeFeedback,
        ]);
    }

    /**
     * Get AI insights summary across the system
     */
    public function aiInsights()
    {
        // Classrooms with AI analysis
        $classroomsWithAI = Classroom::whereNotNull('ai_analysis')
            ->orWhereNotNull('ai_recommendation')
            ->with(['professor:id,name'])
            ->get()
            ->map(function ($classroom) {
                return [
                    'classroom_id' => $classroom->id,
                    'classroom_name' => $classroom->name,
                    'subject' => $classroom->subject,
                    'professor_name' => $classroom->professor->name,
                    'ai_analysis' => $classroom->ai_analysis,
                    'ai_recommendation' => $classroom->ai_recommendation,
                    'status' => $classroom->status,
                ];
            });

        // Summary statistics
        $totalClassrooms = Classroom::count();
        $classroomsWithAnalysis = Classroom::whereNotNull('ai_analysis')->count();
        $classroomsWithRecommendations = Classroom::whereNotNull('ai_recommendation')->count();

        return response()->json([
            'summary' => [
                'total_classrooms' => $totalClassrooms,
                'with_ai_analysis' => $classroomsWithAnalysis,
                'with_ai_recommendations' => $classroomsWithRecommendations,
                'ai_coverage_percentage' => $totalClassrooms > 0 ? round(($classroomsWithAnalysis / $totalClassrooms) * 100, 2) : 0,
            ],
            'ai_insights' => $classroomsWithAI,
        ]);
    }

    /**
     * Export comprehensive system report
     */
    public function exportReport(Request $request)
    {
        $format = $request->get('format', 'json'); // json, csv (future)

        // Compile comprehensive data
        $systemOverview = $this->systemOverview()->getData();
        $professorStats = $this->professorAnalytics($request)->getData();
        $subjectStats = $this->subjectAnalytics()->getData();
        $studentEngagement = $this->studentEngagement()->getData();

        $report = [
            'generated_at' => Carbon::now()->toISOString(),
            'system_overview' => $systemOverview,
            'professor_analytics' => $professorStats,
            'subject_analytics' => $subjectStats,
            'student_engagement' => $studentEngagement,
        ];

        return response()->json($report);
    }
}
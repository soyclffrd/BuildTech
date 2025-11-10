<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Workers: only their enrollments; Trainers/Admins: can see all
        $query = Enrollment::with(['worker', 'course']);

        if ($user->role === 'worker') {
            $query->where('worker_id', $user->id);
        }

        $enrollments = $query->get();

        // Shape response if worker expects detailed enrollment list
        if ($user->role === 'worker') {
            $data = $enrollments
                ->filter(function ($enrollment) {
                    // Only include enrollments where course exists and has a trainer
                    return $enrollment->course && $enrollment->course->trainer_id;
                })
                ->map(function ($enrollment) {
                    $progress = $enrollment->progress_percentage ?? 0;
                    $course = $enrollment->course;
                    return [
                        'id' => $enrollment->id,
                        'course_id' => $enrollment->course_id,
                        'course' => $course->title,
                        'course_title' => $course->title,
                        'progress' => rtrim(rtrim(number_format((float)$progress, 2, '.', ''), '0'), '.') . '%',
                        'status' => $enrollment->status ?? 'pending',
                        'course_status' => $course->status,
                        'enrollment_limit' => $course->enrollment_limit,
                        'enrollment_count' => $course->enrollments()->count(),
                    ];
                })
                ->values(); // Re-index array after filtering

            return response()->json($data);
        }

        return response()->json($enrollments);
    }

    public function store(Request $request): JsonResponse
    {
        // Only workers can enroll
        if (!Auth::check() || Auth::user()->role !== 'worker') {
            return response()->json(['message' => 'Forbidden: workers only'], 403);
        }

        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $course = Course::findOrFail($request->course_id);

        // Check if course is full
        if ($course->enrollment_limit) {
            $currentEnrollments = $course->enrollments()->count();
            if ($currentEnrollments >= $course->enrollment_limit) {
                return response()->json(['message' => 'Course is full'], 400);
            }
        }

        // Check if already enrolled
        $existingEnrollment = Enrollment::where('worker_id', Auth::id())
            ->where('course_id', $request->course_id)
            ->first();

        if ($existingEnrollment) {
            return response()->json(['message' => 'Already enrolled in this course'], 400);
        }

        // Check if course is approved (workers can only enroll in approved courses)
        if ($course->status !== 'approved') {
            return response()->json(['message' => 'Course is not available for enrollment'], 400);
        }

        $enrollment = Enrollment::create([
            'worker_id' => Auth::id(),
            'course_id' => $request->course_id,
            'status' => 'approved', // Auto-approve enrollment - workers can start immediately
        ]);

        return response()->json($enrollment, 201);
    }

    /**
     * Get progress for all enrolled courses (Worker only)
     * Returns course_id, course_title, completed_lessons, total_lessons
     */
    public function progress(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Only workers can view their progress
        if ($user->role !== 'worker') {
            return response()->json(['message' => 'Forbidden: workers only'], 403);
        }

        // Get all enrollments for this worker with course and lessons
        // Only include enrollments where course exists and has a trainer
        $enrollments = Enrollment::where('worker_id', $user->id)
            ->with(['course.lessons'])
            ->whereHas('course', function ($query) {
                $query->whereNotNull('trainer_id');
            })
            ->get();

        $progress = $enrollments
            ->filter(function ($enrollment) {
                // Only include enrollments where course exists and has a trainer
                return $enrollment->course && $enrollment->course->trainer_id;
            })
            ->map(function ($enrollment) {
                $course = $enrollment->course;
                $totalLessons = $course->lessons->count();
                
                // Calculate completed lessons based on progress_percentage
                // If progress_percentage is set, use it; otherwise default to 0
                $progressPercentage = $enrollment->progress_percentage ?? 0;
                $completedLessons = $totalLessons > 0 
                    ? (int) round(($progressPercentage / 100) * $totalLessons)
                    : 0;

                return [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'completed_lessons' => $completedLessons,
                    'total_lessons' => $totalLessons,
                ];
            })
            ->values(); // Re-index array after filtering

        return response()->json($progress);
    }
}

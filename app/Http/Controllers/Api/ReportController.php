<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Course;
use App\Models\Assessment;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Get activities report
     * Returns worker-course activities with last activity timestamp
     */
    public function activities(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Check if user has permission to view reports (admin or trainer)
        if (!in_array($user->role, ['admin', 'trainer'])) {
            return response()->json(['message' => 'Forbidden: admins and trainers only'], 403);
        }

        // Collect all activities (enrollments, assessments, certificates)
        $activities = collect();

        // Get enrollments with worker and course
        $enrollments = Enrollment::with(['worker', 'course'])->get();
        foreach ($enrollments as $enrollment) {
            if ($enrollment->worker && $enrollment->course) {
                $activities->push([
                    'worker_id' => $enrollment->worker_id,
                    'worker_name' => $enrollment->worker->name,
                    'course_id' => $enrollment->course_id,
                    'course_title' => $enrollment->course->title,
                    'activity_date' => $enrollment->created_at,
                ]);
            }
        }

        // Get assessments with worker and course
        $assessments = Assessment::with(['worker', 'course'])->get();
        foreach ($assessments as $assessment) {
            if ($assessment->worker && $assessment->course) {
                $activities->push([
                    'worker_id' => $assessment->worker_id,
                    'worker_name' => $assessment->worker->name,
                    'course_id' => $assessment->course_id,
                    'course_title' => $assessment->course->title,
                    'activity_date' => $assessment->created_at,
                ]);
            }
        }

        // Get certificates with worker and course
        $certificates = Certificate::with(['worker', 'course'])->get();
        foreach ($certificates as $certificate) {
            if ($certificate->worker && $certificate->course) {
                $activities->push([
                    'worker_id' => $certificate->worker_id,
                    'worker_name' => $certificate->worker->name,
                    'course_id' => $certificate->course_id,
                    'course_title' => $certificate->course->title,
                    'activity_date' => $certificate->created_at,
                ]);
            }
        }

        // Group by worker_id and course_id, get the latest activity for each combination
        $grouped = $activities->groupBy(function ($item) {
            return $item['worker_id'] . '-' . $item['course_id'];
        });

        // Build the final result with latest activity for each worker-course pair
        $result = $grouped->map(function ($group) {
            $latest = $group->sortByDesc('activity_date')->first();
            return [
                'worker' => $latest['worker_name'],
                'course' => $latest['course_title'],
                'last_activity' => $latest['activity_date']->format('Y-m-d H:i'),
            ];
        })->values()->all();

        // Sort by last_activity descending
        usort($result, function ($a, $b) {
            return strtotime($b['last_activity']) - strtotime($a['last_activity']);
        });

        return response()->json($result);
    }

    /**
     * Get performance report
     * Returns worker performance with completed courses and average score
     */
    public function performance(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Check if user has permission to view reports (admin or trainer)
        if (!in_array($user->role, ['admin', 'trainer'])) {
            return response()->json(['message' => 'Forbidden: admins and trainers only'], 403);
        }

        // Get all workers
        $workers = User::where('role', 'worker')->get();
        $result = [];

        foreach ($workers as $worker) {
            // Count completed courses (certificates issued)
            $completedCourses = Certificate::where('worker_id', $worker->id)->count();
            
            // Calculate average score from all assessments
            $assessments = Assessment::where('worker_id', $worker->id)->get();
            $averageScore = 0;
            
            if ($assessments->count() > 0) {
                $averageScore = round($assessments->avg('score'), 0);
            }

            $result[] = [
                'worker' => $worker->name,
                'completed_courses' => $completedCourses,
                'average_score' => (int) $averageScore,
            ];
        }

        // Sort by average_score descending, then by completed_courses descending
        usort($result, function ($a, $b) {
            if ($b['average_score'] === $a['average_score']) {
                return $b['completed_courses'] - $a['completed_courses'];
            }
            return $b['average_score'] - $a['average_score'];
        });

        return response()->json($result);
    }
}


<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AssessmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Assessment::with(['worker', 'course'])->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'score' => 'required|integer|min:0|max:100',
            'remarks' => 'nullable|string',
        ]);

        $assessment = Assessment::create([
            'worker_id' => Auth::id(),
            'course_id' => $request->course_id,
            'score' => $request->score,
            'remarks' => $request->remarks,
        ]);

        return response()->json($assessment, 201);
    }

    /**
     * Submit an assessment for a specific course (Worker only)
     * Returns message and result (passed/failed)
     */
    public function submit(Request $request, string $course): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Only workers can submit assessments
        if ($user->role !== 'worker') {
            return response()->json(['message' => 'Forbidden: workers only'], 403);
        }

        $request->validate([
            'score' => 'required|integer|min:0|max:100',
        ]);

        // Check if worker is enrolled in the course
        $enrollment = \App\Models\Enrollment::where('worker_id', $user->id)
            ->where('course_id', $course)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You must be enrolled in this course to submit an assessment'], 403);
        }

        // Check if assessment already exists for this worker and course
        $existingAssessment = Assessment::where('worker_id', $user->id)
            ->where('course_id', $course)
            ->first();

        if ($existingAssessment) {
            // Update existing assessment
            $existingAssessment->update([
                'score' => $request->score,
                'completed_at' => now(),
            ]);
            $assessment = $existingAssessment;
        } else {
            // Create new assessment
            $assessment = Assessment::create([
                'worker_id' => $user->id,
                'course_id' => $course,
                'score' => $request->score,
                'completed_at' => now(),
            ]);
        }

        // Determine if passed (assuming passing score is 70 or higher)
        $passingScore = 70;
        $result = $assessment->score >= $passingScore ? 'passed' : 'failed';

        return response()->json([
            'message' => 'Assessment submitted successfully',
            'result' => $result,
        ]);
    }
}

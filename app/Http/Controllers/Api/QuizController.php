<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    /**
     * Get all quizzes for a course
     */
    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $courseId = $request->query('course_id');

        $query = Quiz::with(['questions', 'course', 'trainer']);

        if ($user->role === 'trainer') {
            // Trainers can only see their own quizzes
            $query->where('trainer_id', $user->id);
        }

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $quizzes = $query->get();
        return response()->json($quizzes);
    }

    /**
     * Get a specific quiz with questions
     */
    public function show(string $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $quiz = Quiz::with(['questions', 'course', 'trainer'])->findOrFail($id);

        // Check access
        if ($user->role === 'trainer' && $quiz->trainer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($quiz);
    }

    /**
     * Create a new quiz (Trainer only)
     */
    public function store(Request $request): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'trainer') {
            return response()->json(['message' => 'Forbidden: trainers only'], 403);
        }

        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0|max:100',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.correct_answer' => 'required|integer|min:0',
            'questions.*.points' => 'nullable|integer|min:1',
            'questions.*.order' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();
        $course = \App\Models\Course::findOrFail($request->course_id);

        // Verify trainer owns the course
        if ($course->trainer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden: You can only create quizzes for your own courses'], 403);
        }

        // Create quiz
        $quiz = Quiz::create([
            'course_id' => $request->course_id,
            'trainer_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'time_limit' => $request->time_limit,
            'passing_score' => $request->passing_score ?? 70,
            'is_active' => true,
        ]);

        // Create questions
        foreach ($request->questions as $index => $questionData) {
            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $questionData['question'],
                'options' => $questionData['options'],
                'correct_answer' => $questionData['correct_answer'],
                'points' => $questionData['points'] ?? 1,
                'order' => $questionData['order'] ?? $index,
            ]);
        }

        return response()->json($quiz->load('questions'), 201);
    }

    /**
     * Update a quiz (Trainer only)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'trainer') {
            return response()->json(['message' => 'Forbidden: trainers only'], 403);
        }

        $user = Auth::user();
        $quiz = Quiz::findOrFail($id);

        // Verify trainer owns the quiz
        if ($quiz->trainer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden: You can only update your own quizzes'], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'questions' => 'sometimes|array|min:1',
            'questions.*.question' => 'required|string',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.correct_answer' => 'required|integer|min:0',
            'questions.*.points' => 'nullable|integer|min:1',
            'questions.*.order' => 'nullable|integer|min:0',
        ]);

        // Update quiz
        $quizData = [];
        if ($request->has('title')) {
            $quizData['title'] = $request->title;
        }
        if ($request->has('description')) {
            $quizData['description'] = $request->description;
        }
        if ($request->has('time_limit')) {
            $quizData['time_limit'] = $request->time_limit;
        }
        if ($request->has('passing_score')) {
            $quizData['passing_score'] = $request->passing_score;
        }
        if ($request->has('is_active')) {
            $quizData['is_active'] = $request->is_active;
        }

        if (!empty($quizData)) {
            $quiz->update($quizData);
        }

        // Update questions if provided
        if ($request->has('questions')) {
            // Delete existing questions
            $quiz->questions()->delete();

            // Create new questions
            foreach ($request->questions as $index => $questionData) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => $questionData['question'],
                    'options' => $questionData['options'],
                    'correct_answer' => $questionData['correct_answer'],
                    'points' => $questionData['points'] ?? 1,
                    'order' => $questionData['order'] ?? $index,
                ]);
            }
        }

        return response()->json($quiz->fresh()->load('questions'));
    }

    /**
     * Delete a quiz (Trainer only)
     */
    public function destroy(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'trainer') {
            return response()->json(['message' => 'Forbidden: trainers only'], 403);
        }

        $user = Auth::user();
        $quiz = Quiz::findOrFail($id);

        // Verify trainer owns the quiz
        if ($quiz->trainer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden: You can only delete your own quizzes'], 403);
        }

        $quiz->delete();

        return response()->json(['message' => 'Quiz deleted successfully']);
    }
}


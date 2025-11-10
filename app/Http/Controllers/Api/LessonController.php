<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     * Requires authentication - filters lessons based on user role:
     * - Workers: Lessons from approved courses only
     * - Trainers: Lessons from their courses + approved courses
     * - Admins: All lessons
     */
    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $query = Lesson::with('course');

        // Filter lessons based on course visibility
        switch ($user->role) {
            case 'worker':
                // Workers can only see lessons from approved courses
                $query->whereHas('course', function ($q) {
                    $q->where('status', 'approved');
                });
                break;

            case 'trainer':
                // Trainers can see lessons from their courses + approved courses
                $query->whereHas('course', function ($q) use ($user) {
                    $q->where(function ($query) use ($user) {
                        $query->where('trainer_id', $user->id)
                              ->orWhere('status', 'approved');
                    });
                });
                break;

            case 'admin':
                // Admins can see all lessons
                // No filter needed
                break;

            default:
                return response()->json(['message' => 'Invalid user role'], 403);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'duration' => 'nullable|integer|min:0',
            'order' => 'nullable|integer|min:0',
        ]);

        // Check if user can add lessons to this course
        $course = Course::findOrFail($request->course_id);
        
        if ($user->role === 'trainer') {
            // Trainers can only add lessons to their own courses
            if ($course->trainer_id !== $user->id) {
                return response()->json(['message' => 'Forbidden: You can only add lessons to your own courses'], 403);
            }
        } elseif ($user->role !== 'admin') {
            // Only trainers and admins can create lessons
            return response()->json(['message' => 'Forbidden: Only trainers and admins can create lessons'], 403);
        }

        $lessonData = $request->only(['course_id', 'title', 'content', 'duration', 'order']);
        
        // Handle file upload if present
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('lessons', $fileName, 'public');
            $lessonData['file_path'] = '/storage/' . $filePath;
        }

        $lesson = Lesson::create($lessonData);

        return response()->json($lesson, 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(Lesson::findOrFail($id));
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $lesson = Lesson::findOrFail($id);
        $course = $lesson->course;

        // Check if user can update this lesson
        if ($user->role === 'trainer') {
            // Trainers can only update lessons in their own courses
            if ($course->trainer_id !== $user->id) {
                return response()->json(['message' => 'Forbidden: You can only update lessons in your own courses'], 403);
            }
        } elseif ($user->role !== 'admin') {
            // Only trainers and admins can update lessons
            return response()->json(['message' => 'Forbidden: Only trainers and admins can update lessons'], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'duration' => 'nullable|integer|min:0',
            'order' => 'nullable|integer|min:0',
        ]);

        // Get all lesson fields from request
        // For JSON requests, use json()->all() to get the JSON body
        // For form data, use all()
        $allInput = $request->isJson() ? $request->json()->all() : $request->all();
        
        \Log::info('Lesson Update Request', [
            'lesson_id' => $id,
            'user_id' => $user->id,
            'method' => $request->method(),
            'allInput' => $allInput,
            'isJson' => $request->isJson(),
            'hasFile' => $request->hasFile('file'),
            'request_all' => $request->all(),
        ]);
        
        $lessonData = [];
        
        // Process all fields - use input() which works for both JSON and FormData
        // Title is required, so always include it if present
        if ($request->input('title') !== null) {
            $title = trim($request->input('title'));
            if (!empty($title)) {
                $lessonData['title'] = $title;
            }
        }
        
        // Content can be empty, so include it if the key exists
        if ($request->input('content') !== null) {
            $lessonData['content'] = $request->input('content');
        }
        
        // Handle duration - blank means unlimited (null)
        // Always process duration if it's present in the request (even if empty)
        if ($request->has('duration')) {
            $duration = $request->input('duration');
            // Convert empty string, null, or 0 to null (unlimited)
            if ($duration === '' || $duration === null || $duration === '0' || $duration === 0) {
                $lessonData['duration'] = null;
            } else {
                $lessonData['duration'] = (int)$duration;
            }
        }
        
        // Handle order field
        if ($request->input('order') !== null) {
            $order = $request->input('order');
            if ($order === '' || $order === null) {
                $lessonData['order'] = null;
            } else {
                $lessonData['order'] = (int)$order;
            }
        }
        
        // Handle file upload if present
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('lessons', $fileName, 'public');
            $lessonData['file_path'] = '/storage/' . $filePath;
        }

        \Log::info('Lesson Update Data Prepared', [
            'lessonData' => $lessonData, 
            'lesson_before' => $lesson->toArray(),
            'fields_count' => count($lessonData),
            'duration_in_request' => $request->input('duration'),
            'duration_processed' => $lessonData['duration'] ?? 'not_set'
        ]);

        // Always update if we have data to update
        // Note: Even if duration is set to null (unlimited), we should update it
        // Check if we have any fields to update (including null values)
        if (count($lessonData) > 0) {
            $result = $lesson->update($lessonData);
            \Log::info('Lesson Update Result', [
                'lesson_id' => $lesson->id,
                'updated_fields' => array_keys($lessonData),
                'update_result' => $result,
                'lesson_after' => $lesson->fresh()->toArray(),
                'duration_after_update' => $lesson->fresh()->duration
            ]);
        } else {
            \Log::warning('No lesson update data provided', [
                'allInput' => $allInput,
                'request_all' => $request->all(),
                'request_keys' => array_keys($request->all()),
                'has_duration' => $request->has('duration'),
                'duration_value' => $request->input('duration')
            ]);
        }
        
        return response()->json($lesson->fresh());
    }

    public function destroy($id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $lesson = Lesson::findOrFail($id);
        $course = $lesson->course;

        // Check if user can delete this lesson
        if ($user->role === 'trainer') {
            // Trainers can only delete lessons in their own courses
            if ($course->trainer_id !== $user->id) {
                return response()->json(['message' => 'Forbidden: You can only delete lessons in your own courses'], 403);
            }
        } elseif ($user->role !== 'admin') {
            // Only trainers and admins can delete lessons
            return response()->json(['message' => 'Forbidden: Only trainers and admins can delete lessons'], 403);
        }

        $lesson->delete();
        return response()->json(['message' => 'Lesson deleted']);
    }
}

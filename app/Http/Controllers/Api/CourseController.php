<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     * Requires authentication - filters courses based on user role:
     * - Workers: Only approved courses
     * - Trainers: Their own courses + all approved courses
     * - Admins: All courses
     */
    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $query = Course::with(['trainer', 'enrollments']);

        // Always filter to only show courses uploaded by trainers (have trainer_id)
        $query->whereNotNull('trainer_id');

        // Filter based on user role
        switch ($user->role) {
            case 'worker':
                // Workers can only see approved courses uploaded by trainers
                $query->where('status', 'approved');
                break;

            case 'trainer':
                // Trainers can see their own courses + all approved courses
                $query->where(function ($q) use ($user) {
                    $q->where('trainer_id', $user->id)
                      ->orWhere('status', 'approved');
                });
                break;

            case 'admin':
                // Admins can see all courses (but still only those with trainers)
                // No additional filter needed
                break;

            default:
                return response()->json(['message' => 'Invalid user role'], 403);
        }

        $courses = $query->get()->map(function ($course) {
            $course->enrollment_count = $course->enrollments->count();
            $course->is_full = $course->enrollment_limit ? $course->enrollment_count >= $course->enrollment_limit : false;
            return $course;
        });

        return response()->json($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'trainer') {
        	return response()->json(['message' => 'Forbidden: trainers only'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string'
        ]);

        $course = Course::create([
            'trainer_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category
        ]);

        return response()->json($course, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $course = Course::with('lessons')->findOrFail($id);
        return response()->json($course);
    }

    /**
     * Get lessons for a specific course.
     * Requires authentication - access control based on user role:
     * - Workers: Only if course is approved
     * - Trainers: Only if course is theirs or approved
     * - Admins: All courses
     */
    public function lessons(string $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $course = Course::findOrFail($id);

        // Check access based on user role
        switch ($user->role) {
            case 'worker':
                // Workers can only see lessons from approved courses
                if ($course->status !== 'approved') {
                    return response()->json(['message' => 'Forbidden: Course not approved'], 403);
                }
                break;

            case 'trainer':
                // Trainers can see lessons from their own courses (any status) + approved courses
                // If it's their own course, allow access regardless of status
                if ($course->trainer_id === $user->id) {
                    // Trainer owns this course - allow access
                    break;
                }
                // Not their course - only allow if approved
                if ($course->status !== 'approved') {
                    return response()->json(['message' => 'Forbidden: You can only access your own courses or approved courses'], 403);
                }
                break;

            case 'admin':
                // Admins can see all courses
                // No check needed
                break;

            default:
                return response()->json(['message' => 'Invalid user role'], 403);
        }

        // Get lessons ordered by 'order' field if it exists, otherwise by id
        $lessons = $course->lessons()->orderBy('order')->orderBy('id')->get();

        return response()->json([
            'course' => $course->load('trainer'),
            'lessons' => $lessons,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $course = Course::findOrFail($id);

        // Only trainers (for their own courses) or admins can update
        if ($user->role !== 'admin' && ($user->role !== 'trainer' || $course->trainer_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Log the incoming request for debugging
        \Log::info('Course Update Request', [
            'course_id' => $id,
            'user_id' => $user->id,
            'request_all' => $request->all(),
            'request_json' => $request->json()->all(),
            'content_type' => $request->header('Content-Type'),
        ]);

        $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'enrollment_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        // Prepare update data - only include editable fields
        // Status is preserved automatically since we don't include it in the update
        $updateData = [];
        
        // For JSON requests, use json()->all() to get the JSON body
        // For form data, use all()
        $allInput = $request->isJson() ? $request->json()->all() : $request->all();
        
        \Log::info('Parsed Input Data', ['allInput' => $allInput, 'isJson' => $request->isJson()]);
        
        // Always include title if present
        if (isset($allInput['title'])) {
            $updateData['title'] = trim($allInput['title']);
        }
        
        // Always include description if present (can be empty)
        if (isset($allInput['description'])) {
            $updateData['description'] = $allInput['description'];
        }
        
        // Always include category if present
        if (isset($allInput['category'])) {
            $updateData['category'] = trim($allInput['category']);
        }
        
        // Handle numeric field - convert empty strings to null
        if (isset($allInput['enrollment_limit'])) {
            $enrollmentLimit = $allInput['enrollment_limit'];
            $updateData['enrollment_limit'] = ($enrollmentLimit === '' || $enrollmentLimit === null || $enrollmentLimit === 0) ? null : (int)$enrollmentLimit;
        }
        
        \Log::info('Update Data Prepared', ['updateData' => $updateData, 'course_before' => $course->toArray()]);
        
        // Important: For trainers editing approved courses, the status remains 'approved'
        // Only admins can change course status through the approve/reject endpoints
        // This ensures approved courses don't go back to pending when trainers edit them
        // We explicitly DO NOT include 'status' in $updateData to preserve it
        
        // Update the course with all provided fields
        if (!empty($updateData)) {
            $result = $course->update($updateData);
            \Log::info('Course Update Result', [
                'course_id' => $course->id, 
                'updated_fields' => array_keys($updateData),
                'update_result' => $result,
                'course_after' => $course->fresh()->toArray()
            ]);
        } else {
            \Log::warning('No update data provided', ['allInput' => $allInput]);
        }
        
        $updatedCourse = $course->fresh()->load('trainer');
        \Log::info('Course After Update', ['course' => $updatedCourse->toArray()]);
        
        return response()->json($updatedCourse);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $course = Course::findOrFail($id);

        // Only trainers (for their own courses) or admins can delete
        if ($user->role !== 'admin' && ($user->role !== 'trainer' || $course->trainer_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }

    /**
     * Approve a course (Admin only)
     */
    public function approve(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $course = Course::findOrFail($id);
        
        $course->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Course approved successfully',
            'course' => $course->load(['trainer', 'approver']),
        ]);
    }

    /**
     * Reject a course (Admin only)
     */
    public function reject(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $course = Course::findOrFail($id);
        
        $course->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Course rejected',
            'course' => $course->load(['trainer', 'approver']),
        ]);
    }
}

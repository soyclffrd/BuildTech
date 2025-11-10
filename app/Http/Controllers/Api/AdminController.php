<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    /**
     * Get all trainers
     */
    public function trainers(): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $trainers = User::where('role', 'trainer')
            ->withCount('courses')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($trainers);
    }

    /**
     * Approve a trainer
     */
    public function approveTrainer(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $trainer = User::where('id', $id)->where('role', 'trainer')->firstOrFail();
        $trainer->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Trainer approved successfully',
            'trainer' => $trainer->fresh(),
        ]);
    }

    /**
     * Reject a trainer
     */
    public function rejectTrainer(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $trainer = User::where('id', $id)->where('role', 'trainer')->firstOrFail();
        $trainer->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Trainer rejected successfully',
            'trainer' => $trainer->fresh(),
        ]);
    }

    /**
     * Get all workers
     */
    public function workers(): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $workers = User::where('role', 'worker')
            ->withCount(['enrollments', 'certificates', 'assessments'])
            ->get();

        return response()->json($workers);
    }

    /**
     * Create a new trainer
     */
    public function createTrainer(Request $request): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $trainer = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'trainer',
            'status' => 'approved', // Admin-created trainers are auto-approved
        ]);

        return response()->json([
            'message' => 'Trainer created successfully',
            'trainer' => $trainer,
        ], 201);
    }

    /**
     * Create a new worker
     */
    public function createWorker(Request $request): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $worker = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'worker',
            'status' => 'approved', // Admin-created workers are auto-approved
        ]);

        return response()->json([
            'message' => 'Worker created successfully',
            'worker' => $worker,
        ], 201);
    }

    /**
     * Create a new admin
     */
    public function createAdmin(Request $request): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $admin = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'admin',
            'status' => 'approved', // Admin-created admins are auto-approved
        ]);

        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => $admin,
        ], 201);
    }

    /**
     * Update a trainer
     */
    public function updateTrainer(Request $request, string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $trainer = User::where('id', $id)->where('role', 'trainer')->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $trainer->update($validated);

        return response()->json([
            'message' => 'Trainer updated successfully',
            'trainer' => $trainer->fresh(),
        ]);
    }

    /**
     * Delete a trainer
     */
    public function deleteTrainer(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $trainer = User::where('id', $id)->where('role', 'trainer')->firstOrFail();
        $trainer->delete();

        return response()->json(['message' => 'Trainer deleted successfully']);
    }

    /**
     * Update a worker
     */
    public function updateWorker(Request $request, string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $worker = User::where('id', $id)->where('role', 'worker')->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $worker->update($validated);

        return response()->json([
            'message' => 'Worker updated successfully',
            'worker' => $worker->fresh(),
        ]);
    }

    /**
     * Delete a worker
     */
    public function deleteWorker(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $worker = User::where('id', $id)->where('role', 'worker')->firstOrFail();
        $worker->delete();

        return response()->json(['message' => 'Worker deleted successfully']);
    }

    /**
     * Approve enrollment
     */
    public function approveEnrollment(string $id): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $enrollment = \App\Models\Enrollment::findOrFail($id);
        $enrollment->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Enrollment approved successfully',
            'enrollment' => $enrollment->fresh(),
        ]);
    }

    /**
     * Get platform metrics
     */
    public function metrics(): JsonResponse
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: admins only'], 403);
        }

        $metrics = [
            'total_users' => User::count(),
            'total_workers' => User::where('role', 'worker')->count(),
            'total_trainers' => User::where('role', 'trainer')->count(),
            'pending_trainers' => User::where('role', 'trainer')->where('status', 'pending')->count(),
            'total_courses' => \App\Models\Course::count(),
            'pending_courses' => \App\Models\Course::where('status', 'pending')->count(),
            'approved_courses' => \App\Models\Course::where('status', 'approved')->count(),
            'total_enrollments' => \App\Models\Enrollment::count(),
            'pending_enrollments' => \App\Models\Enrollment::where('status', 'pending')->count(),
            'total_certificates' => \App\Models\Certificate::count(),
            'inactive_users' => User::where('role', 'worker')
                ->whereDoesntHave('enrollments', function ($query) {
                    $query->where('created_at', '>=', now()->subDays(30));
                })
                ->count(),
            'low_performing_courses' => \App\Models\Course::withCount(['enrollments', 'certificates'])
                ->get()
                ->filter(function ($course) {
                    $enrollmentCount = $course->enrollments_count;
                    $certificateCount = $course->certificates_count;
                    // Low performing: less than 20% completion rate or less than 5 enrollments
                    return $enrollmentCount > 0 && (
                        ($certificateCount / $enrollmentCount) < 0.2 || 
                        $enrollmentCount < 5
                    );
                })
                ->count(),
        ];

        return response()->json($metrics);
    }
}

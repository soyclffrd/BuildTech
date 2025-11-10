<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    public function index(): JsonResponse
    {
        $certificates = Certificate::with(['worker', 'course'])->get();
        return response()->json($certificates);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = Auth::user();
        $course = Course::findOrFail($request->course_id);

        $pdf = App::make('dompdf.wrapper')->loadView('pdf.certificate', compact('user', 'course'));

        $filePath = 'certificates/' . $user->id . '-' . $course->id . '.pdf';
        Storage::disk('public')->put($filePath, $pdf->output());

        $certificate = Certificate::create([
            'worker_id' => $user->id,
            'course_id' => $course->id,
            'certificate_path' => $filePath,
        ]);

        return response()->json([
            'message' => 'Certificate generated successfully',
            'certificate' => $certificate,
        ]);
    }

    /**
     * Retrieve certificate for a specific worker and course
     * Route: GET /api/certificates/{worker}/{course}
     */
    public function show(string $worker, string $course): JsonResponse
    {
        $certificate = Certificate::where('worker_id', $worker)
            ->where('course_id', $course)
            ->with(['worker', 'course'])
            ->first();

        if (!$certificate) {
            return response()->json([
                'message' => 'Certificate not found'
            ], 404);
        }

        return response()->json([
            'certificate' => $certificate,
            'file' => 'storage/' . $certificate->certificate_path,
        ]);
    }

    /**
     * Generate certificate for a specific worker and course
     * Route: POST /api/certificates/{worker}/{course}
     */
    public function generateForWorker(string $worker, string $course): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Only admins and trainers can generate certificates for workers
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'trainer'])) {
            return response()->json(['message' => 'Forbidden: admins and trainers only'], 403);
        }

        // Find worker and course
        $workerModel = User::findOrFail($worker);
        $courseModel = Course::findOrFail($course);

        // Check if worker has passed the assessment (score >= 70)
        $assessment = Assessment::where('worker_id', $worker)
            ->where('course_id', $course)
            ->first();

        if (!$assessment || $assessment->score < 70) {
            return response()->json([
                'message' => 'Worker must pass the assessment (score >= 70) to receive a certificate'
            ], 403);
        }

        // Check if certificate already exists
        $existingCertificate = Certificate::where('worker_id', $worker)
            ->where('course_id', $course)
            ->first();

        if ($existingCertificate) {
            // Return existing certificate
            return response()->json([
                'message' => 'Certificate already exists',
                'file' => 'storage/' . $existingCertificate->certificate_path,
            ]);
        }

        // Generate PDF
        try {
            $pdf = App::make('dompdf.wrapper')->loadView('pdf.certificate', [
                'user' => $workerModel,
                'course' => $courseModel
            ]);

            // Create file name: WorkerName_CourseName.pdf
            $workerName = str_replace(' ', '_', $workerModel->name);
            $courseName = str_replace(' ', '_', $courseModel->title);
            $fileName = $workerName . '_' . $courseName . '.pdf';
            $filePath = 'certificates/' . $fileName;

            Storage::disk('public')->put($filePath, $pdf->output());

            // Create certificate record
            $certificate = Certificate::create([
                'worker_id' => $worker,
                'course_id' => $course,
                'certificate_path' => $filePath,
                'issued_at' => now(),
            ]);

            return response()->json([
                'message' => 'Certificate generated successfully',
                'file' => 'storage/' . $filePath,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error generating certificate: ' . $e->getMessage()
            ], 500);
        }
    }
}

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\{
    CourseController,
    LessonController,
    EnrollmentController,
    AssessmentController,
    CertificateController,
    ReportController,
    UserController,
    AdminController,
    QuizController
};

Route::middleware(['auth:sanctum'])->group(function () {

    // 🧱 Courses (protected - requires authentication)
    Route::get('courses', [CourseController::class, 'index']);
    Route::apiResource('courses', CourseController::class)->except(['index']);
    Route::get('courses/{course}/lessons', [CourseController::class, 'lessons']);
    Route::match(['get', 'post'], 'courses/{course}/approve', [CourseController::class, 'approve']);
    Route::match(['get', 'post'], 'courses/{course}/reject', [CourseController::class, 'reject']);

    // 📖 Lessons (protected - requires authentication)
    Route::get('lessons', [LessonController::class, 'index']);
    Route::apiResource('lessons', LessonController::class)->except(['index']);

    // 🧾 Enrollments
    Route::post('enroll', [EnrollmentController::class, 'store']); // legacy
    Route::post('enrollments', [EnrollmentController::class, 'store']);
    Route::get('enrollments', [EnrollmentController::class, 'index']);
    Route::get('progress', [EnrollmentController::class, 'progress']);

    // 🧩 Assessments (Worker submissions)
    Route::apiResource('assessments', AssessmentController::class);
    Route::post('assessments/{course}/submit', [AssessmentController::class, 'submit']);

    // 📝 Quizzes (Trainer-created quizzes with questions)
    Route::get('quizzes', [QuizController::class, 'index']);
    Route::post('quizzes', [QuizController::class, 'store']);
    Route::get('quizzes/{id}', [QuizController::class, 'show']);
    Route::put('quizzes/{id}', [QuizController::class, 'update']);
    Route::delete('quizzes/{id}', [QuizController::class, 'destroy']);

    // 🏆 Certificates
    Route::get('certificates', [CertificateController::class, 'index']);
    Route::post('certificates/generate', [CertificateController::class, 'generate']);
    Route::get('certificates/{worker}/{course}', [CertificateController::class, 'show']);
    Route::post('certificates/{worker}/{course}', [CertificateController::class, 'generateForWorker']);

    // 📊 Reports
    Route::get('reports/activities', [ReportController::class, 'activities']);
    Route::get('reports/performance', [ReportController::class, 'performance']);

    // 👤 User Profile
    Route::get('user/profile', [UserController::class, 'show']);
    Route::put('user/profile', [UserController::class, 'update']);

    // 👨‍💼 Admin Management
    Route::prefix('admin')->group(function () {
        Route::get('trainers', [AdminController::class, 'trainers']);
        Route::post('trainers', [AdminController::class, 'createTrainer']);
        Route::put('trainers/{id}', [AdminController::class, 'updateTrainer']);
        Route::delete('trainers/{id}', [AdminController::class, 'deleteTrainer']);
        Route::post('trainers/{id}/approve', [AdminController::class, 'approveTrainer']);
        Route::post('trainers/{id}/reject', [AdminController::class, 'rejectTrainer']);
        
        Route::get('workers', [AdminController::class, 'workers']);
        Route::post('workers', [AdminController::class, 'createWorker']);
        Route::put('workers/{id}', [AdminController::class, 'updateWorker']);
        Route::delete('workers/{id}', [AdminController::class, 'deleteWorker']);
        
        Route::post('admins', [AdminController::class, 'createAdmin']);
        
        Route::post('enrollments/{id}/approve', [AdminController::class, 'approveEnrollment']);
        
        Route::get('metrics', [AdminController::class, 'metrics']);
    });
});

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});


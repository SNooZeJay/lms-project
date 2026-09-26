<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CertificateController as AdminCertificateController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Catalog\CourseCatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Instructor\CourseController;
use App\Http\Controllers\Instructor\CurriculumController;
use App\Http\Controllers\Instructor\QuizController as InstructorQuizController;
use App\Http\Controllers\MaterialDownloadController;
use App\Http\Controllers\Role\AdministratorController;
use App\Http\Controllers\Role\InstructorController;
use App\Http\Controllers\Role\StudentController;
use App\Http\Controllers\Student\CertificateController as StudentCertificateController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\PaymentController as StudentPaymentController;
use App\Http\Controllers\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// The provider calls this endpoint, so it is public and signed, not session
// authenticated. It is registered before the authenticated groups on purpose.
Route::post('/webhooks/paymongo', PayMongoWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paymongo');

// TEMPORARY. The dashboard will not accept an edited endpoint URL while it
// still has deliveries queued, and the queued ones are addressed to the
// placeholder path from its own example, which is /webhook. This alias lets
// those in-flight deliveries land while the dashboard is sorted out.
//
// Remove this once the saved endpoint URL is /webhooks/paymongo and the queue
// has drained. It is a shim for a dashboard limitation, not a second
// supported address.
Route::post('/webhook', PayMongoWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paymongo.placeholder_path');

Route::get('/courses', [CourseCatalogController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [CourseCatalogController::class, 'show'])->name('courses.show');

$authenticated = ['auth', 'account.active', 'verified', 'password.change'];

Route::middleware($authenticated)->group(function (): void {
    Route::get('/account/profile', [ProfileController::class, 'show'])->name('account.profile');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/password', [PasswordController::class, 'show'])->name('account.password');
    Route::post('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
});

Route::middleware([...$authenticated, 'role:student'])->group(function (): void {
    Route::get('/student', StudentController::class)->name('student.dashboard');
    Route::get('/student/courses', [EnrollmentController::class, 'index'])->name('student.courses.index');
    Route::get('/student/courses/{course}', [EnrollmentController::class, 'show'])->name('student.courses.show');
    Route::get('/student/courses/{course}/lessons/{lesson}', [EnrollmentController::class, 'showLesson'])->name('student.lessons.show');
    Route::post('/student/courses/{course}/lessons/{lesson}/complete', [EnrollmentController::class, 'completeLesson'])->name('student.lessons.complete');
    Route::get('/student/courses/{course}/lessons/{lesson}/materials/{material}/download', [MaterialDownloadController::class, 'show'])->name('student.materials.download');
    Route::get('/student/courses/{course}/quizzes/{quiz}', [StudentQuizController::class, 'show'])->name('student.quizzes.show');
    Route::post('/student/courses/{course}/quizzes/{quiz}/start', [StudentQuizController::class, 'start'])->name('student.quizzes.start');
    Route::get('/student/courses/{course}/quizzes/{quiz}/attempts/{attempt}', [StudentQuizController::class, 'attempt'])->name('student.quizzes.attempts.show');
    Route::post('/student/courses/{course}/quizzes/{quiz}/attempts/{attempt}/submit', [StudentQuizController::class, 'submit'])->name('student.quizzes.attempts.submit');
    Route::get('/student/courses/{course}/quizzes/{quiz}/attempts/{attempt}/result', [StudentQuizController::class, 'result'])->name('student.quizzes.attempts.result');
    Route::get('/student/certificates', [StudentCertificateController::class, 'index'])->name('student.certificates.index');
    Route::get('/student/certificates/{certificate}', [StudentCertificateController::class, 'show'])->name('student.certificates.show');
    Route::post('/student/courses/{course}/complete', [StudentCertificateController::class, 'complete'])->name('student.courses.complete');
    Route::post('/student/courses/{course}/checkout', [StudentPaymentController::class, 'checkout'])->name('student.payments.checkout');
    Route::get('/student/courses/{course}/checkout/return', [StudentPaymentController::class, 'return'])->name('student.payments.return');
    Route::post('/student/courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('student.enrollments.store');
});

Route::middleware([...$authenticated, 'role:instructor'])->group(function (): void {
    Route::get('/instructor', InstructorController::class)->name('instructor.dashboard');
    Route::get('/instructor/courses', [CourseController::class, 'index'])->name('instructor.courses.index');
    Route::get('/instructor/courses/new', [CourseController::class, 'create'])->name('instructor.courses.create');
    Route::post('/instructor/courses', [CourseController::class, 'store'])->name('instructor.courses.store');
    Route::get('/instructor/courses/{course}', [CourseController::class, 'show'])->name('instructor.courses.show');
    Route::get('/instructor/courses/{course}/edit', [CourseController::class, 'edit'])->name('instructor.courses.edit');
    Route::patch('/instructor/courses/{course}', [CourseController::class, 'update'])->name('instructor.courses.update');
    Route::post('/instructor/courses/{course}/publish', [CourseController::class, 'publish'])->name('instructor.courses.publish');
    Route::post('/instructor/courses/{course}/unpublish', [CourseController::class, 'unpublish'])->name('instructor.courses.unpublish');
    Route::post('/instructor/courses/{course}/archive', [CourseController::class, 'archive'])->name('instructor.courses.archive');
    Route::post('/instructor/courses/{course}/restore', [CourseController::class, 'restore'])->name('instructor.courses.restore');
    Route::post('/instructor/courses/{course}/modules', [CurriculumController::class, 'storeModule'])->name('instructor.courses.modules.store');
    Route::post('/instructor/courses/{course}/quizzes', [InstructorQuizController::class, 'store'])->name('instructor.courses.quizzes.store');
    Route::patch('/instructor/courses/{course}/quizzes/{quiz}', [InstructorQuizController::class, 'update'])->name('instructor.courses.quizzes.update');
    Route::post('/instructor/courses/{course}/quizzes/{quiz}/publish', [InstructorQuizController::class, 'publish'])->name('instructor.courses.quizzes.publish');
    Route::post('/instructor/courses/{course}/quizzes/{quiz}/archive', [InstructorQuizController::class, 'archive'])->name('instructor.courses.quizzes.archive');
    Route::post('/instructor/courses/{course}/quizzes/{quiz}/questions', [InstructorQuizController::class, 'storeQuestion'])->name('instructor.courses.quizzes.questions.store');
    Route::patch('/instructor/courses/{course}/modules/reorder', [CurriculumController::class, 'reorderModules'])->name('instructor.courses.modules.reorder');
    Route::patch('/instructor/courses/{course}/modules/{module}/lessons/reorder', [CurriculumController::class, 'reorderLessons'])->name('instructor.courses.modules.lessons.reorder');
    Route::patch('/instructor/courses/{course}/modules/{module}', [CurriculumController::class, 'updateModule'])->name('instructor.courses.modules.update');
    Route::get('/instructor/courses/{course}/modules/{module}/edit', [CurriculumController::class, 'editModule'])->name('instructor.courses.modules.edit');
    Route::post('/instructor/courses/{course}/modules/{module}/lessons', [CurriculumController::class, 'storeLesson'])->name('instructor.courses.modules.lessons.store');
    Route::post('/instructor/courses/{course}/modules/{module}/archive', [CurriculumController::class, 'archiveModule'])->name('instructor.courses.modules.archive');
    Route::post('/instructor/courses/{course}/modules/{module}/restore', [CurriculumController::class, 'restoreModule'])->name('instructor.courses.modules.restore');
    Route::post('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/archive', [CurriculumController::class, 'archiveLesson'])->name('instructor.courses.modules.lessons.archive');
    Route::post('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/restore', [CurriculumController::class, 'restoreLesson'])->name('instructor.courses.modules.lessons.restore');
    Route::get('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/edit', [CurriculumController::class, 'editLesson'])->name('instructor.courses.modules.lessons.edit');
    Route::patch('/instructor/courses/{course}/modules/{module}/lessons/{lesson}', [CurriculumController::class, 'updateLesson'])->name('instructor.courses.modules.lessons.update');
    Route::post('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/materials', [CurriculumController::class, 'storeMaterial'])->name('instructor.courses.materials.store');
    Route::get('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/materials/{material}/edit', [CurriculumController::class, 'editMaterial'])->name('instructor.courses.materials.edit');
    Route::patch('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/materials/{material}', [CurriculumController::class, 'updateMaterial'])->name('instructor.courses.materials.update');
    Route::get('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/materials/{material}/download', [MaterialDownloadController::class, 'show'])->name('instructor.courses.materials.download');
});

Route::middleware([...$authenticated, 'role:administrator'])->group(function (): void {
    Route::get('/admin', AdministratorController::class)->name('administrator.dashboard');
    Route::get('/admin/materials/{material}/download', [MaterialDownloadController::class, 'show'])->name('admin.materials.download');
    Route::get('/admin/certificates', [AdminCertificateController::class, 'index'])->name('admin.certificates.index');
    Route::get('/admin/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
    Route::post('/admin/certificates/{certificate}/revoke', [AdminCertificateController::class, 'revoke'])->name('admin.certificates.revoke');
    Route::post('/admin/certificates/{certificate}/reissue', [AdminCertificateController::class, 'reissue'])->name('admin.certificates.reissue');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::patch('/admin/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.role.update');
    Route::patch('/admin/users/{user}/status', [UserController::class, 'updateStatus'])->name('admin.users.status.update');
    Route::get('/admin/activity', [ActivityLogController::class, 'index'])->name('admin.activity.index');
});

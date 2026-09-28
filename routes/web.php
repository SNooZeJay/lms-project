<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CertificateController as AdminCertificateController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Catalog\CourseCatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Instructor\CourseController;
use App\Http\Controllers\Instructor\CurriculumController;
use App\Http\Controllers\Instructor\QuizController as InstructorQuizController;
use App\Http\Controllers\MaterialDownloadController;
use App\Http\Controllers\Messaging\ConversationController;
use App\Http\Controllers\Messaging\SupportRequestController;
use App\Http\Controllers\Notification\NotificationCentreController;
use App\Http\Controllers\Notification\NotificationReadController;
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

Route::get('/courses', [CourseCatalogController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [CourseCatalogController::class, 'show'])->name('courses.show');

// Public, because the sign in and sign up pages link to them. A link to a page
// that does not exist is worse than having no link at all, so these are real
// pages with content taken from what the application actually does.
Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');

$authenticated = ['auth', 'account.active', 'verified', 'password.change'];

Route::middleware($authenticated)->group(function (): void {
    Route::get('/account/profile', [ProfileController::class, 'show'])->name('account.profile');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/password', [PasswordController::class, 'show'])->name('account.password');
    Route::post('/account/password', [PasswordController::class, 'update'])->name('account.password.update');

    // Read state is available to every signed in role, so these sit in the
    // shared group rather than in any one role's group. A PATCH rather than a
    // POST, because marking a notice read changes a stored row and is not a
    // request to be resubmitted.
    Route::patch('/notifications/{notification}/read', [NotificationReadController::class, 'update'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationReadController::class, 'updateAll'])->name('notifications.read-all');
    Route::get('/notifications', NotificationCentreController::class)->name('notifications.index');

    // Messaging. In the shared group because every role has a thread list, and
    // which threads a role can open is decided by ConversationPolicy rather than
    // by which group a route sits in.
    Route::get('/messages', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/messages/{conversation}', [ConversationController::class, 'store'])->name('conversations.messages.store');
    Route::post('/courses/{course}/messages', [ConversationController::class, 'storeCourseThread'])->name('conversations.course.store');
    Route::patch('/messages/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');
    Route::patch('/messages/{conversation}/reopen', [ConversationController::class, 'reopen'])->name('conversations.reopen');
    Route::patch('/messages/{conversation}/archive', [ConversationController::class, 'archive'])->name('conversations.archive');

    // Support. Raising one is open to any active account, and only the person
    // who raised it and an administrator can read it. Both halves are decided by
    // ConversationPolicy rather than by which group the route sits in, because a
    // support thread is reachable by whoever raised it and that is not a role.
    Route::get('/support', [SupportRequestController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportRequestController::class, 'store'])->name('support.store');

    // Announcements. Reading is open to any active account, because what a person
    // may read is an enrollment question rather than a role one, and
    // Announcement::scopeVisibleTo answers it. Publishing is role bound and lives
    // in the role groups below, so an instructor reaches only the course form and
    // an administrator only the platform one.
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
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

    // A course announcement, into a course the instructor owns. The course is
    // the subject, so the path says which course and the Policy says whether it
    // is theirs. An administrator has no equivalent route, because administration
    // is not teaching.
    Route::post('/instructor/courses/{course}/announcements', [AnnouncementController::class, 'storeCourse'])
        ->name('instructor.courses.announcements.store');
    Route::get('/instructor/courses', [CourseController::class, 'index'])->name('instructor.courses.index');
    Route::get('/instructor/courses/new', [CourseController::class, 'create'])->name('instructor.courses.create');
    Route::post('/instructor/courses', [CourseController::class, 'store'])->name('instructor.courses.store');
    Route::get('/instructor/courses/{course}', [CourseController::class, 'show'])->name('instructor.courses.show');
    Route::get('/instructor/courses/{course}/edit', [CourseController::class, 'edit'])->name('instructor.courses.edit');

    // Declared here rather than at the end of the block, because it belongs with
    // the other pages of one Course. An Instructor looking for the roster looks
    // for it on the Course, not in a list of unrelated endpoints.
    Route::get('/instructor/courses/{course}/students', [CourseController::class, 'students'])->name('instructor.courses.students');
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

    // Support requests. Inside the administrator group as well as behind
    // ConversationPolicy, so the role is refused by the middleware and then
    // again by the policy. Two checks that must agree is better than one that
    // can be moved.
    Route::get('/admin/support', [SupportRequestController::class, 'index'])->name('admin.support.index');

    // Joining is what lets an administrator read and answer a request, and it is
    // a separate step rather than a side effect of opening the page. The
    // participant row is the grant, so making it deliberate means the record
    // shows who picked the request up and when the person who raised it was
    // told.
    Route::post('/admin/support/{conversation}/join', [SupportRequestController::class, 'join'])->name('admin.support.join');

    // A platform announcement, to everybody. Administration is not teaching, so
    // there is deliberately no course form in this group: that belongs to the
    // instructor who owns the course.
    Route::post('/admin/announcements', [AnnouncementController::class, 'storePlatform'])->name('admin.announcements.store');
});

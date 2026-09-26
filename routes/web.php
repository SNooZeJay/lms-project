<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Instructor\CourseController;
use App\Http\Controllers\Instructor\CurriculumController;
use App\Http\Controllers\Role\AdministratorController;
use App\Http\Controllers\Role\InstructorController;
use App\Http\Controllers\Role\StudentController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

$authenticated = ['auth', 'account.active', 'verified', 'password.change'];

Route::middleware($authenticated)->group(function (): void {
    Route::get('/account/profile', [ProfileController::class, 'show'])->name('account.profile');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/password', [PasswordController::class, 'show'])->name('account.password');
    Route::post('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
});

Route::middleware([...$authenticated, 'role:student'])->group(function (): void {
    Route::get('/student', StudentController::class)->name('student.dashboard');
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
    Route::post('/instructor/courses/{course}/modules', [CurriculumController::class, 'storeModule'])->name('instructor.courses.modules.store');
    Route::patch('/instructor/courses/{course}/modules/{module}', [CurriculumController::class, 'updateModule'])->name('instructor.courses.modules.update');
    Route::get('/instructor/courses/{course}/modules/{module}/edit', [CurriculumController::class, 'editModule'])->name('instructor.courses.modules.edit');
    Route::post('/instructor/courses/{course}/modules/{module}/lessons', [CurriculumController::class, 'storeLesson'])->name('instructor.courses.modules.lessons.store');
    Route::get('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/edit', [CurriculumController::class, 'editLesson'])->name('instructor.courses.modules.lessons.edit');
    Route::patch('/instructor/courses/{course}/modules/{module}/lessons/{lesson}', [CurriculumController::class, 'updateLesson'])->name('instructor.courses.modules.lessons.update');
    Route::post('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/materials', [CurriculumController::class, 'storeMaterial'])->name('instructor.courses.materials.store');
    Route::get('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/materials/{material}/edit', [CurriculumController::class, 'editMaterial'])->name('instructor.courses.materials.edit');
    Route::patch('/instructor/courses/{course}/modules/{module}/lessons/{lesson}/materials/{material}', [CurriculumController::class, 'updateMaterial'])->name('instructor.courses.materials.update');
});

Route::middleware([...$authenticated, 'role:administrator'])->group(function (): void {
    Route::get('/admin', AdministratorController::class)->name('administrator.dashboard');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::patch('/admin/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.role.update');
    Route::patch('/admin/users/{user}/status', [UserController::class, 'updateStatus'])->name('admin.users.status.update');
    Route::get('/admin/activity', [ActivityLogController::class, 'index'])->name('admin.activity.index');
});

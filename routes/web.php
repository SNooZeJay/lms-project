<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HomeController;
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
});

Route::middleware([...$authenticated, 'role:administrator'])->group(function (): void {
    Route::get('/admin', AdministratorController::class)->name('administrator.dashboard');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::patch('/admin/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.role.update');
    Route::patch('/admin/users/{user}/status', [UserController::class, 'updateStatus'])->name('admin.users.status.update');
    Route::get('/admin/activity', [ActivityLogController::class, 'index'])->name('admin.activity.index');
});

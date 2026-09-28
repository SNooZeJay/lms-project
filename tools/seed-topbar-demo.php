<?php

/*
 | Puts a few real notifications in the development database so the topbar bell
 | can be seen doing its job.
 |
 | They go in through RecordNotification, which is the only writer, so they are
 | exactly the rows the automation slices will produce: same table, same types,
 | same dedup behaviour. Nothing is inserted behind the seam's back.
 |
 | Remove them with:  php tools/seed-topbar-demo.php --clean
 */

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$clean = in_array('--clean', $argv, true);

/*
 | Found by role, not by address.
 |
 | These three lookups used to name the email address of each account, which
 | quietly meant "the developer" rather than "the administrator". When two
 | accounts exchanged addresses the script kept working and started seeding the
 | wrong people, because a row was still found at the old address. A role cannot
 | be reassigned by accident, so a role is what gets asked for here.
 */
$byRole = function (string $role) {
    return User::query()
        ->whereHas('profile', fn ($query) => $query->where('role', $role))
        ->orderBy('id')
        ->first();
};

$admin = $byRole(UserRole::Administrator->value);
$student = $byRole(UserRole::Student->value);
$instructor = $byRole(UserRole::Instructor->value);

if ($admin === null || $student === null || $instructor === null) {
    fwrite(STDERR, "One of the three development roles has no account, so there is nobody to notify.\n");

    exit(1);
}

printf(
    "  seeding: admin id=%d, instructor id=%d, student id=%d\n",
    $admin->id,
    $instructor->id,
    $student->id
);

if ($clean) {
    $removed = DB::table('notifications')->whereIn('title', [
        'A new course was published',
        'Certificate issued',
        'Your enrollment is active',
        'A student enrolled in your course',
        'Quiz attempt needs review',
    ])->delete();

    fwrite(STDERR, "\n  removed {$removed} demo notifications\n");

    exit(0);
}

$record = new RecordNotification;
$course = Course::query()->where('status', 'published')->first();

$record->handle(
    $admin,
    NotificationType::SystemAnnouncement,
    'A new course was published',
    'Introduction to Information Technology is now open for enrollment.',
    dedupKey: 'demo:system:new-course',
);

$record->handle(
    $admin,
    NotificationType::CertificateAvailable,
    'Certificate issued',
    'A certificate was issued for Introduction to Information Technology.',
    course: $course,
    subjectType: 'certificate',
    subjectId: 1,
);

$record->handle(
    $admin,
    NotificationType::CourseEnrollment,
    'Your enrollment is active',
    'You are enrolled in Introduction to Information Technology.',
    course: $course,
    subjectType: 'enrollment',
    subjectId: 1,
);

$record->handle(
    $instructor,
    NotificationType::CourseEnrollment,
    'A student enrolled in your course',
    'Shan Lee Kian Garmino enrolled in Introduction to Information Technology.',
    course: $course,
    subjectType: 'enrollment',
    subjectId: 1,
);

$record->handle(
    $instructor,
    NotificationType::QuizFailed,
    'Quiz attempt needs review',
    'A student did not pass the latest check and may need a retake.',
    course: $course,
    subjectType: 'quiz',
    subjectId: 1,
);

$counts = [];

foreach (['administrator' => $admin, 'instructor' => $instructor, 'student' => $student] as $role => $user) {
    $counts[$role] = Notification::unreadCountFor($user);
}

fwrite(STDERR, "\n  unread counts\n");

foreach ($counts as $role => $count) {
    fwrite(STDERR, sprintf("    %-14s %d\n", $role, $count));
}

fwrite(STDERR, "\n  remove them with: php tools/seed-topbar-demo.php --clean\n");

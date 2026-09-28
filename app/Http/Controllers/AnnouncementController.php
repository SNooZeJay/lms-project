<?php

namespace App\Http\Controllers;

use App\Actions\Announcements\PublishAnnouncement;
use App\Actions\Notifications\MarkNotificationRead;
use App\Enums\NotificationType;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Reading announcements, publishing them, and withdrawing one.
 *
 * The list is built from Announcement::scopeVisibleTo, which is the same
 * enrollment-scoped query the read check asks about one row. That is deliberate:
 * a list that filtered afterwards and a policy that filtered beforehand would be
 * two rules, and a hand-typed id would be judged by the second one alone.
 */
class AnnouncementController extends Controller
{
    /**
     * Everything this person may be shown, newest first.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->visibleTo($user)
            ->with(['course', 'author'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('announcements.index', [
            'announcements' => $announcements,
            'readAnnouncementIds' => $this->readIdsFor($user, $announcements->pluck('id')),
        ]);
    }

    /**
     * One announcement.
     *
     * Opening it marks the reader's notice for it read, because read state lives
     * on the notice and nothing else would ever set it. A person who has read an
     * announcement and then sees it still bold in their list has been given a
     * state that means nothing.
     */
    public function show(Request $request, Announcement $announcement): RedirectResponse|View
    {
        Gate::forUser($request->user())->authorize('view', $announcement);

        $notice = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('subject_type', 'announcement')
            ->where('subject_id', $announcement->id)
            ->first();

        if ($notice !== null && $notice->read_at === null) {
            app(MarkNotificationRead::class)
                ->handle($request->user(), $notice);
        }

        return view('announcements.show', [
            'announcement' => $announcement->load(['course', 'author']),
        ]);
    }

    /**
     * Publish into one course, as its instructor.
     */
    public function storeCourse(StoreAnnouncementRequest $request, Course $course): RedirectResponse
    {
        $validated = $request->validated();

        $announcement = app(PublishAnnouncement::class)->courseAnnouncement(
            $request->user(),
            $course,
            $validated['title'],
            $validated['body'],
        );

        return redirect()
            ->route('announcements.show', $announcement)
            ->with('status', 'Announcement published.');
    }

    /**
     * Publish to everybody, as an administrator.
     */
    public function storePlatform(StoreAnnouncementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $announcement = app(PublishAnnouncement::class)->platformAnnouncement(
            $request->user(),
            $validated['title'],
            $validated['body'],
        );

        return redirect()
            ->route('announcements.show', $announcement)
            ->with('status', 'Announcement published to everybody.');
    }

    /**
     * Withdraw one, by its author.
     */
    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        app(PublishAnnouncement::class)->withdraw($request->user(), $announcement);

        return redirect()
            ->route('announcements.index')
            ->with('status', 'Announcement withdrawn.');
    }

    /**
     * Which of the listed announcements this person has already read.
     *
     * One query for the page, keyed by id, so the list can mark each row without
     * asking per row. A read state resolved inside a loop is the per-row query
     * pattern the budget tests exist to fail.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, true>
     */
    private function readIdsFor($user, $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return Notification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [NotificationType::Announcement, NotificationType::SystemAnnouncement])
            ->where('subject_type', 'announcement')
            ->whereIn('subject_id', $ids)
            ->whereNotNull('read_at')
            ->pluck('subject_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }
}

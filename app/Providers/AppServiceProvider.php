<?php

namespace App\Providers;

use App\Console\Commands\CheckProductionReadiness;
use App\Contracts\LocalSecretStore;
use App\Contracts\PayMongoClient;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Notification;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\AnnouncementPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CoursePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\LearningMaterialPolicy;
use App\Policies\LessonPolicy;
use App\Policies\ModulePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\UserPolicy;
use App\Services\Payments\PayMongoApiClient;
use App\Support\WindowsDpapiSecretStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocalSecretStore::class, WindowsDpapiSecretStore::class);

        // The live client is the default. Tests bind a fake so the payment
        // state machine is verifiable without credentials.
        $this->app->bind(PayMongoClient::class, PayMongoApiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([CheckProductionReadiness::class]);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(LearningMaterial::class, LearningMaterialPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);

        /*
         | Two models, one policy.
         |
         | An assignment and the work handed in against it are answered by the same
         | rules: ownership runs through the course either way, and an instructor
         | who may set work on a course may also mark what was handed in on it. Two
         | classes would mean two copies of one sentence that must never disagree.
         |
         | Registered explicitly rather than left to Laravel's convention, because
         | every other policy here is registered explicitly and a convention that
         | happens to work is not the same thing as a dependency that is stated.
         */
        Gate::policy(Assignment::class, AssignmentPolicy::class);
        Gate::policy(AssignmentSubmission::class, AssignmentPolicy::class);

        $this->shareTopbarNotificationCounts();
    }

    /**
     * Give the workspace topbar its notification and message counts.
     *
     * Shared here rather than passed by each controller, because the topbar is
     * on every signed in page and twenty five controllers should not each
     * remember to ask for the same numbers. A count that some pages have and
     * others do not is a badge that lies on the pages that forgot.
     *
     * Four queries, all on an index: two counts that never load a row, and two
     * short pages of five for the two dropdowns. The dashboard query budget test
     * is what keeps that honest.
     */
    private function shareTopbarNotificationCounts(): void
    {
        /*
         | The shell only, deliberately.
         |
         | Registering this for `messages.index` as well was tried, so the list
         | could read the same per thread unread map as the badge instead of
         | computing its own. It made the page worse rather than better: the
         | layout is rendered inside the child view, so a composer listed for
         | both fired twice and the message list went from seven conversation
         | queries to nine.
         |
         | The list therefore keeps its own grouped aggregate: one query, scoped
         | to the threads it is showing. It is the same rule and the same query
         | as the one below, read through Conversation::unreadCountsFor, so the
         | two can differ only in which threads they cover.
         */
        View::composer('layouts.app-shell', function (\Illuminate\Contracts\View\View $view): void {
            $user = Auth::user();

            if ($user === null) {
                return;
            }

            /*
             | One grouped read answers two questions.
             |
             | The badge wants the total unread across every live thread and the
             | panel wants the count for each of the five it lists. Counting them
             | separately meant the badge ran one aggregate and the panel called
             | `unreadCountFor` on each thread, which is two queries apiece, so
             | showing the panel's figures would have cost ten queries on every
             | page of the application.
             |
             | Summing the grouped map gives the same total the badge already
             | showed, so the number on the button and the numbers in the list are
             | one read of the same rows rather than two reads that can disagree.
             */
            $unreadThreads = Conversation::unreadCountsFor($user);

            $view->with([
                'unreadNotifications' => Notification::unreadCountFor($user),
                'recentNotifications' => Notification::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(),
                'unreadMessages' => $unreadThreads->sum(),
                'unreadThreads' => $unreadThreads,
                /*
                 | The last message of each thread.
                 |
                 | Without it the panel listed a course title and a time, so two
                 | threads on two courses were indistinguishable and a reader
                 | holding three unread messages could not tell which thread they
                 | were in. Eager loaded, so it is one query whatever the number of
                 | threads rather than one each.
                 *
                 | The author is not loaded here, and that is a decision. Naming who
                 | wrote the preview is worth a query on the message list, where
                 | there is room for it and the reader is choosing a thread on
                 | purpose. It is not worth a query on every page of the
                 | application for a five row shortcut, and the preview text
                 | already answers the question the shortcut exists to answer.
                 */
                'recentConversations' => Conversation::query()
                    ->forParticipant($user)
                    ->with(['course', 'lastMessage'])
                    ->orderByDesc('last_message_at')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(),
            ]);
        });
    }

    /**
     * Unread messages across every thread the reader is in.
     *
     * One aggregate over two joined, indexed tables rather than a sum in PHP,
     * because the sum loads every thread and every message in it, which grows
     * with the number of conversations and is the pattern the budget test exists
     * to catch.
     */
    private function unreadMessageCount(User $user): int
    {
        return ConversationMessage::query()
            ->join(
                'conversation_participants',
                'conversation_participants.conversation_id',
                '=',
                'conversation_messages.conversation_id'
            )
            ->where('conversation_participants.user_id', $user->id)
            ->whereNull('conversation_participants.archived_at')
            ->where('conversation_messages.author_id', '!=', $user->id)
            ->where(function ($query): void {
                $query->whereNull('conversation_participants.last_read_message_id')
                    ->orWhereColumn('conversation_messages.id', '>', 'conversation_participants.last_read_message_id');
            })
            ->count();
    }
}

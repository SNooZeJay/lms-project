<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\Course;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only place a notification is written.
 *
 * Everything that raises a notice goes through here, so the rules about
 * duplicates, links and course scope are enforced once rather than in every
 * listener. A second writer would be a second set of rules, and the two would
 * drift.
 *
 * A caller supplies the meaning. This class supplies the safety.
 */
final class RecordNotification
{
    /** The dedup key column is 120 characters, and MySQL would truncate silently. */
    private const DEDUP_KEY_MAX = 120;

    /**
     * Raise a notice for one recipient.
     *
     * Returns the stored notification, or null when the notice was suppressed
     * because this recipient already has one with the same dedup key. A null
     * return is the normal outcome of a repeat, not a failure, so a listener
     * does not need to treat it as an error.
     *
     * @param  string  $title  a plain sentence. Stored as written, because it is
     *                         a snapshot of what was true when it was raised.
     * @param  string|null  $body  one or two sentences. Null for a bare notice.
     * @param  string|null  $dedupKey  a stable key for "this exact notice for
     *                                 this exact recipient". Null means never
     *                                 suppress, which is the right choice for a
     *                                 notice that is genuinely repeatable.
     * @param  string|null  $link  a path inside this application.
     * @param  (callable(User): bool)|null  $authorizeLink  decides whether this
     *                                                      recipient may follow the link right now. Required whenever a link
     *                                                      is supplied.
     * @param  string|null  $subjectType  the kind of thing $subjectId names.
     */
    public function handle(
        User $recipient,
        NotificationType $type,
        string $title,
        ?string $body = null,
        ?Course $course = null,
        ?string $dedupKey = null,
        ?string $link = null,
        ?callable $authorizeLink = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): ?Notification {
        $this->guardTitle($title);
        $this->guardCourseScope($type, $course);
        $this->guardDedupKey($dedupKey);

        $link = $this->safeLink($recipient, $link, $authorizeLink);

        try {
            // forceFill rather than create(). The model keeps its columns out of
            // $fillable on purpose, because nothing should be able to set them
            // by handing an array to a constructor. This is the one place that
            // is allowed to write them, so it says so explicitly.
            $notification = new Notification;
            $notification->forceFill([
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'course_id' => $course?->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'link' => $link,
                'dedup_key' => $dedupKey,
                'read_at' => null,
            ]);
            $notification->save();

            return $notification;
        } catch (QueryException $e) {
            // The unique index on (user_id, dedup_key) is the real guarantee. A
            // repeat that arrives while the first write is still in flight
            // loses the race here rather than creating a second notice, and the
            // loser is a repeat, which is exactly the case we wanted to drop.
            if ($this->isDuplicateDedup($e)) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * A title has to say something.
     *
     * An empty notice is worse than no notice, because it occupies a slot in
     * the list and a badge count that the reader cannot act on. This is refused
     * at the seam rather than rendered as a blank row later.
     */
    private function guardTitle(string $title): void
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('A notification needs a title that says something.');
        }

        if (mb_strlen($title) > 160) {
            throw new InvalidArgumentException('A notification title is limited to 160 characters.');
        }
    }

    /**
     * A platform notice may not be pinned to a course.
     *
     * The type already says whether it belongs to a course, so allowing the
     * two to disagree would produce a row that no filter can classify.
     */
    private function guardCourseScope(NotificationType $type, ?Course $course): void
    {
        if ($type->isCourseScoped() && $course === null) {
            throw new InvalidArgumentException("{$type->value} belongs to a course, so a course is required.");
        }

        if (! $type->isCourseScoped() && $course !== null) {
            throw new InvalidArgumentException("{$type->value} is a platform notice, so it cannot belong to a course.");
        }
    }

    private function guardDedupKey(?string $dedupKey): void
    {
        if ($dedupKey === null) {
            return;
        }

        if (trim($dedupKey) === '') {
            throw new InvalidArgumentException('A dedup key is either meaningful or null, never empty.');
        }

        if (mb_strlen($dedupKey) > self::DEDUP_KEY_MAX) {
            // Refused rather than shortened. Two different keys that share a
            // 120 character prefix would silently suppress each other, which is
            // a bug that only appears under load and is very hard to see.
            throw new InvalidArgumentException('A dedup key is limited to '.self::DEDUP_KEY_MAX.' characters.');
        }
    }

    /**
     * Decide the link this recipient will actually be given.
     *
     * Three things can remove a link. A link with a scheme is refused outright,
     * because this column must never carry an off-site address. A relative path
     * is kept, but only if the caller can say this recipient may follow it now.
     * A link the recipient may not follow is dropped and the notice is still
     * delivered, because the information matters even where the destination
     * does not, and a dead or forbidden link is worse than no link.
     */
    private function safeLink(User $recipient, ?string $link, ?callable $authorizeLink): ?string
    {
        if ($link === null) {
            return null;
        }

        $candidate = trim($link);

        if ($candidate === '') {
            return null;
        }

        $this->guardLinkIsLocal($candidate);
        $candidate = $this->toLocalPath($candidate);

        if ($authorizeLink === null) {
            throw new InvalidArgumentException(
                'A notification link needs an authorization check, because a notice must never point '
                .'somebody at a page they are not allowed to open.'
            );
        }

        return $authorizeLink($recipient) ? $candidate : null;
    }

    /**
     * Refuse anything that is not a path inside this application.
     *
     * A scheme, a host, or a protocol relative prefix would turn a notice into
     * an open redirect or a javascript: URI. A listener never needs one, so
     * their presence means something is wrong upstream.
     *
     * An address on this application's own host is allowed and reduced to its
     * path, because route() returns an absolute URL and every caller would
     * otherwise have to remember to convert it. That is a footgun with a crash
     * at the end of it rather than a security control: the first caller that
     * forgot raised an InvalidArgumentException from a notification that was
     * perfectly safe. Comparing against config('app.url') is the same authority
     * PublicHttps uses for the scheme, so the two cannot disagree about what
     * this application's address is.
     */
    private function guardLinkIsLocal(string $link): void
    {
        if (preg_match('#^https?://#i', $link) === 1) {
            $parts = parse_url($link);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $port = $parts['port'] ?? null;

            $appParts = parse_url((string) config('app.url'));
            $appHost = strtolower((string) ($appParts['host'] ?? ''));
            $appPort = $appParts['port'] ?? null;

            $schemeMatches = strtolower((string) ($parts['scheme'] ?? ''))
                === strtolower((string) ($appParts['scheme'] ?? 'http'));

            if ($host !== $appHost || $port !== $appPort || ! $schemeMatches) {
                throw new InvalidArgumentException(
                    "A notification link must stay on this application, got '{$link}'."
                );
            }

            return;
        }

        if (! Str::startsWith($link, '/')) {
            throw new InvalidArgumentException("A notification link must be a path starting with '/', got '{$link}'.");
        }

        if (Str::startsWith($link, '//')) {
            throw new InvalidArgumentException("A notification link must not be protocol relative, got '{$link}'.");
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $link) === 1) {
            throw new InvalidArgumentException("A notification link must not carry a scheme, got '{$link}'.");
        }

        if (preg_match('#[\x00-\x1f\x7f]#', $link) === 1) {
            throw new InvalidArgumentException('A notification link must not contain control characters.');
        }
    }

    /**
     * Reduce an address on this application to the path a link element wants.
     *
     * Stored as a path, so the rendered href cannot carry a host of its own and
     * cannot become an absolute link to somewhere else if the app is later
     * served from a second address.
     */
    private function toLocalPath(string $link): string
    {
        if (! preg_match('#^https?://#i', $link)) {
            return $link;
        }

        $path = (string) (parse_url($link, PHP_URL_PATH) ?: '/');
        $query = parse_url($link, PHP_URL_QUERY);

        return $query !== null ? $path.'?'.$query : $path;
    }

    /**
     * Whether this failure is the unique index doing its job.
     *
     * Only the dedup index counts. Any other constraint failure is a real fault
     * and must not be swallowed, or a broken foreign key would look like a
     * suppressed repeat.
     */
    private function isDuplicateDedup(QueryException $e): bool
    {
        if (! Str::contains(Str::lower($e->getMessage()), 'unique')) {
            return false;
        }

        return Str::contains($e->getMessage(), 'notifications_user_dedup_unique')
            || Str::contains($e->getMessage(), 'user_id, dedup_key');
    }
}

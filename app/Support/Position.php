<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Hands out the next free position for an ordered child list.
 *
 * Modules, lessons, materials, quizzes and questions are ordered by an integer
 * position under a unique constraint on (owner, position). Appending therefore
 * has to answer two questions at once: what is the next number, and may this
 * request have it.
 *
 * Asking for the maximum is the easy half and it is not safe on its own. Two
 * "add module" requests that arrive together both read the same maximum, both
 * decide on the same number, and one of them loses on the unique constraint and
 * shows the instructor a server error for an action they simply repeated. That
 * is the most common concurrency fault in an editor of this shape.
 *
 * So the parent row is locked first, inside the caller's transaction. Every
 * append to the same list then queues behind that one row, reads the maximum
 * after the previous append has committed, and gets a different number. The
 * lock is on a single row that already exists, so nothing is created and
 * nothing has to be cleaned up if the transaction rolls back.
 */
final class Position
{
    /**
     * The next free position for a child of $owner.
     *
     * @param  HasMany<covariant Model, Model>  $children
     *
     * @throws LogicException when called outside a transaction, where a row lock
     *                        would be released immediately and silently protect
     *                        nothing.
     */
    public static function reserve(Model $owner, HasMany $children): int
    {
        if (DB::transactionLevel() < 1) {
            // A lock taken outside a transaction is released the moment the
            // statement finishes, so the read below would race exactly as it did
            // before. Failing loudly here is better than a reservation that
            // looks safe and is not.
            throw new LogicException(
                'A position must be reserved inside a transaction, or the parent row lock is released before the row is read.'
            );
        }

        $owner->newQuery()
            ->whereKey($owner->getKey())
            ->lockForUpdate()
            ->first();

        return ((int) $children->max('position')) + 1;
    }
}

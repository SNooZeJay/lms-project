<?php

namespace App\Enums;

/**
 * Whether a thread is still being worked on.
 *
 * A closed thread is read only. Closing is what an administrator does to a
 * support conversation that has been dealt with, and it is deliberately not a
 * delete: the record of what was said is the part that matters.
 */
enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}

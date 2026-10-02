<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Work a student hands in against an assignment.
 *
 * The states are the reason this table exists in its own right:
 *
 *   pending   submitted, waiting for checking. This is what a student sees, in
 *             those words, and it is true until an instructor says otherwise.
 *   graded    a mark and feedback exist. The student can see both.
 *   returned  handed back to be redone. Submitting again moves the row back to
 *             pending, so "waiting for checking" becomes true again without the
 *             old mark being left on screen.
 *
 * A mark is never derived. `score` is the number the instructor typed, against
 * that assignment's own `max_score`, and the interface reads both rather than
 * turning them into a percentage on the application's behalf.
 */
class AssignmentSubmission extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const GRADED = 'graded';

    public const RETURNED = 'returned';

    protected $fillable = [
        'assignment_id',
        'student_id',
        'storage_disk',
        'storage_path',
        'original_name',
        'mime_type',
        'byte_size',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who marked it, if anyone.
     *
     * Null on delete rather than cascade: a submission is the record of a
     * student's work, and it must survive the removal of the staff account that
     * happened to grade it. The mark stays; the name of whoever typed it does
     * not.
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Is this waiting for an instructor?
     *
     * The one thing the student's dashboard asks about this row.
     */
    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isGraded(): bool
    {
        return $this->status === self::GRADED;
    }

    /**
     * Has this been handed back to be redone?
     */
    public function isReturned(): bool
    {
        return $this->status === self::RETURNED;
    }

    /**
     * The mark as a percentage, or null when there is no mark yet.
     *
     * Only ever called when a mark exists, and rounded for display rather than
     * stored: a percentage is a way of reading a mark, not the mark itself, and
     * storing both invites them to disagree.
     */
    public function percentage(): ?int
    {
        $maximum = (float) $this->assignment?->max_score;

        if ($this->score === null || $maximum <= 0) {
            return null;
        }

        return (int) round(((float) $this->score / $maximum) * 100);
    }
}

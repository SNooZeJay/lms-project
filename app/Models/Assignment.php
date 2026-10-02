<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work an instructor sets, against one lesson.
 *
 * The instructor supplies three things and the student answers one:
 *
 *   instructions  required. What is being asked, in the instructor's words.
 *   form_url      optional. A Google Form, for work that suits a form.
 *   briefing      optional PDF. A brief that is longer than the instructions.
 *
 * `max_score` is the instructor's own scale and is nullable, because a brief may
 * exist before the instructor has decided how it is marked. When it is null the
 * assignment is still readable and still submittable, and only the mark is
 * unavailable.
 *
 * Publication is a status rather than a boolean, because a brief is drafted
 * before it is released and a released brief is sometimes withdrawn, and a
 * boolean cannot tell those three apart from two.
 */
class Assignment extends Model
{
    use HasFactory;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const CLOSED = 'closed';

    protected $fillable = [
        'lesson_id',
        'created_by',
        'title',
        'instructions',
        'form_url',
        'briefing_disk',
        'briefing_path',
        'briefing_mime_type',
        'briefing_byte_size',
        'max_score',
        'status',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'briefing_byte_size' => 'integer',
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The instructor who set it.
     *
     * Delete is restricted rather than cascading: a brief belongs to the school,
     * not to one person's account, and losing a brief because an instructor left
     * would be a bad trade.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    /**
     * The instructor's queue: what is waiting, oldest first.
     *
     * A pending submission only. A graded one is history and returning it to the
     * top of the queue would hide what still needs attention behind work that has
     * already been done.
     */
    public function pendingSubmissions(): HasMany
    {
        return $this->submissions()
            ->where('status', AssignmentSubmission::PENDING)
            ->oldest('submitted_at');
    }

    /**
     * Has the instructor decided how this is marked?
     */
    public function isMarkable(): bool
    {
        return $this->max_score !== null && $this->max_score > 0;
    }
}

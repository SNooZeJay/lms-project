<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'summary',
        'content_text',
        'is_required',
        'estimated_minutes',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => ContentStatus::class,
            'is_required' => 'boolean',
            'estimated_minutes' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function learningMaterials(): HasMany
    {
        return $this->hasMany(LearningMaterial::class);
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }

    /**
     * Work set against this lesson.
     *
     * Every assignment, drafts included, because the only reader that is not a
     * student is the instructor authoring the lesson and they need to see all of
     * it. `publishedAssignments()` is the one a student sees, and the difference
     * is a method rather than a check at each call site, because a check at each
     * call site is a check that eventually gets forgotten on the one page that
     * matters.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Work a student may read.
     *
     * Published and closed, in that order of recency rather than of importance:
     * a closed brief is still readable, because the student who was given it
     * still needs the question they answered and the mark they were given.
     * Drafts are excluded, because a draft is the instructor's own note.
     */
    public function publishedAssignments(): HasMany
    {
        return $this->assignments()
            ->whereIn('assignments.status', [Assignment::PUBLISHED, Assignment::CLOSED])
            // Oldest first, so the work reads in the order it was set.
            //
            // Newest first is the more common default and it is wrong here. A
            // student works down this list, and a list that reorders every time
            // an instructor adds something means a student who left halfway down
            // comes back to a different page. The instructor's queue sorts the
            // other way, oldest first as well, and for the same reason: the thing
            // that has been waiting longest is the thing to do next.
            ->orderBy('assignments.created_at')
            ->orderBy('assignments.id');
    }
}

<?php

namespace App\Models;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Services\Storage\CourseCoverStorage;
use App\Support\CourseCoverCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'learning_objectives',
        'category',
        'level',
        'course_type',
    ];

    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'course_type' => CourseType::class,
            'status' => CourseStatus::class,
            'price_minor' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /* ------------------------------------------------------------- the cover */

    /**
     * Whether this course has a cover at all.
     *
     * Three states rather than two, and the difference matters to every caller. A
     * course can have no cover, an uploaded file of its own, or a photograph chosen
     * from the catalog, and the three are displayed differently and served from
     * different places.
     */
    public function hasCover(): bool
    {
        return filled($this->thumbnail_path);
    }

    public function hasUploadedCover(): bool
    {
        return $this->hasCover() && $this->cover_source === 'upload';
    }

    public function hasChosenCover(): bool
    {
        return $this->hasCover() && $this->cover_source === 'unsplash';
    }

    /**
     * The address of a cover this application serves itself, or null.
     *
     * Null for a chosen photograph on purpose: that one is served from Unsplash and
     * its address is built at the size the caller needs rather than read from here,
     * so a card and a detail page do not request the same pixels. Null also for a row
     * whose file has gone, which the storage service detects, so a missing file
     * becomes a placeholder rather than a broken image on every card.
     */
    public function uploadedCoverUrl(): ?string
    {
        if (! $this->hasUploadedCover()) {
            return null;
        }

        return app(CourseCoverStorage::class)->urlFor($this);
    }

    /**
     * The address of a chosen photograph at a size, or null.
     *
     * Built from the identifier rather than stored whole, so the same photograph is
     * requested at whatever width the place it appears in needs. A card in a list of
     * twelve asking for a photograph sized for a hero is the difference between a
     * page that loads and a page that does not.
     */
    public function chosenCoverUrl(int $width, int $height, int $quality = 72): ?string
    {
        if (! $this->hasChosenCover()) {
            return null;
        }

        return CourseCoverCatalog::urlFor((string) $this->thumbnail_path, $width, $height, $quality);
    }

    /**
     * The text to put in an alt attribute for this course's cover.
     *
     * Not the course title. A cover beside a title that already says the same thing
     * is read twice, and a screen reader announcing "Introduction to Information
     * Technology, Introduction to Information Technology" is worse than saying
     * nothing. What is described is the picture, and the course name is already
     * available as the heading that follows it.
     */
    public function coverAltText(): string
    {
        if ($this->hasChosenCover()) {
            $photograph = CourseCoverCatalog::find((string) $this->thumbnail_path);

            return $photograph['shows'] ?? 'Course cover photograph';
        }

        return 'Cover image for '.$this->title;
    }

    /**
     * Who to credit, for a cover that is somebody else's work.
     *
     * Null for an upload, which is the instructor's own file and carries no
     * attribution. Null also when the photograph is a placeholder, which is a local
     * asset rather than a photograph.
     */
    public function coverCreditName(): ?string
    {
        return $this->hasChosenCover() ? $this->cover_credit_name : null;
    }

    public function coverCreditUrl(): ?string
    {
        return $this->hasChosenCover() ? $this->cover_credit_url : null;
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}

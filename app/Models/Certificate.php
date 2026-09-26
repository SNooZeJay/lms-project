<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CertificateStatus::class,
            'completion_date' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_certificate_id');
    }

    public function replacement(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_certificate_id');
    }

    public function isValid(): bool
    {
        return $this->status === CertificateStatus::Issued;
    }

    /**
     * The one still-valid certificate for an enrollment.
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', CertificateStatus::Issued);
    }
}

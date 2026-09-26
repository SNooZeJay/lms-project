<?php

namespace App\Models;

use App\Enums\LearningMaterialType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'material_type',
        'content_text',
        'external_url',
    ];

    protected function casts(): array
    {
        return [
            'material_type' => LearningMaterialType::class,
            'position' => 'integer',
            'byte_size' => 'integer',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

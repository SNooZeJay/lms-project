<?php

namespace App\Models;

use App\Enums\ActivityEventType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor_id',
        'target_user_id',
        'event_type',
        'previous_role',
        'new_role',
        'previous_status',
        'new_status',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ActivityEventType::class,
            'previous_role' => UserRole::class,
            'new_role' => UserRole::class,
            'previous_status' => UserAccountStatus::class,
            'new_status' => UserAccountStatus::class,
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}

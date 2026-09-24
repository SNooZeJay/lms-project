<?php

namespace App\Models;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'bio',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'account_status' => UserAccountStatus::class,
            'must_change_password' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

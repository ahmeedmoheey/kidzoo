<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Child extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'children';

    protected $fillable = [
        'parent_id',
        'name',
        'username',
        'password',
        'age',
        'gender',
        'avatar_url',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(VisualPrediction::class);
    }

    public function chatbotMessages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class);
    }
}

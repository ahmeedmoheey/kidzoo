<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon_url',
        'asset_type',
        'task_type',
        'target_type',
        'skill',
        'min_age',
        'max_age',
        'total_levels',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}

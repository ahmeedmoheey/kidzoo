<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    protected $fillable = [
        'child_id',
        'game_id',
        'difficulty_level',
        'level',
        'started_at',
        'ended_at',
        'duration_sec',
        'trials_count',
        'correct_count',
        'errors_count',
        'missed_count',
        'accuracy',
        'avg_reaction_time_ms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'accuracy' => 'float',
            'avg_reaction_time_ms' => 'float',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function trials(): HasMany
    {
        return $this->hasMany(SessionTrial::class, 'session_id');
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(VisualPrediction::class, 'id', 'session_id');
    }
}

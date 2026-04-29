<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionTrial extends Model
{
    protected $fillable = [
        'session_id',
        'trial_number',
        'task_type',
        'difficulty_level',
        'target_type',
        'stimulus_count',
        'reaction_time_ms',
        'correct',
        'errors',
        'missed_targets',
        'duration_sec',
    ];

    protected function casts(): array
    {
        return [
            'correct' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'session_id');
    }
}

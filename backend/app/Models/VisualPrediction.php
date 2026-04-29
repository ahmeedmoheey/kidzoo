<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisualPrediction extends Model
{
    protected $fillable = [
        'child_id',
        'session_id',
        'status',
        'label',
        'confidence',
        'prob_normal',
        'prob_disorder',
        'weak_skills',
        'training_plan',
        'trials_count',
        'model_version',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'prob_normal' => 'float',
            'prob_disorder' => 'float',
            'weak_skills' => 'array',
            'training_plan' => 'array',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'session_id');
    }
}

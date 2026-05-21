<?php

namespace App\Http\Controllers\Api\ChildApi;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\GameSession;
use App\Models\SessionTrial;
use App\Models\VisualPrediction;
use App\Services\MlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SessionController extends Controller
{
    private const TASK_TYPES = ['Tracking', 'Discrimination', 'Matching', 'Orientation'];

    private const TARGET_TYPES = ['Direction', 'Color', 'Shape', 'Position', 'Animal', 'Object', 'Pattern', 'Sequence', 'Icon'];

    public function __construct(private readonly MlService $ml)
    {
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:50'],
            'difficulty_level' => ['nullable', 'in:Easy,Medium,Hard'],
        ]);

        $session = GameSession::create([
            'child_id' => $request->user()->id,
            'game_id' => $data['game_id'],
            'level' => $data['level'] ?? 1,
            'difficulty_level' => $data['difficulty_level'] ?? 'Easy',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        return response()->json(['message' => 'Session started.', 'session' => $session], 201);
    }

    public function submitTrial(Request $request, GameSession $session): JsonResponse
    {
        abort_unless($session->child_id === $request->user()->id, 403);
        abort_if($session->status !== 'in_progress', 409, 'Session is not in progress.');

        $session->loadMissing('game');

        $data = $request->validate([
            'trial_number' => ['required', 'integer', 'min:1'],
            'task_type' => ['nullable', 'in:' . implode(',', self::TASK_TYPES)],
            'difficulty_level' => ['nullable', 'in:Easy,Medium,Hard'],
            'target_type' => ['nullable', 'in:' . implode(',', self::TARGET_TYPES)],
            'prompt_value' => ['nullable', 'string', 'max:255'],
            'selected_value' => ['nullable', 'string', 'max:255'],
            'stimulus_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'reaction_time_ms' => ['required', 'integer', 'min:0'],
            'correct' => ['required', 'boolean'],
            'errors' => ['required', 'integer', 'min:0'],
            'missed_targets' => ['required', 'integer', 'min:0'],
            'duration_sec' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $trial = $session->trials()->create([
            'trial_number' => $data['trial_number'],
            'task_type' => $data['task_type'] ?? $session->game->task_type,
            'difficulty_level' => $data['difficulty_level'] ?? $session->difficulty_level,
            'target_type' => $data['target_type'] ?? $session->game->target_type,
            'prompt_value' => $data['prompt_value'] ?? null,
            'selected_value' => $data['selected_value'] ?? null,
            'stimulus_count' => $data['stimulus_count'] ?? 1,
            'reaction_time_ms' => $data['reaction_time_ms'],
            'correct' => $data['correct'],
            'errors' => $data['errors'],
            'missed_targets' => $data['missed_targets'],
            'duration_sec' => $data['duration_sec'],
            'metadata' => $data['metadata'] ?? null,
        ]);

        return response()->json(['message' => 'Trial recorded.', 'trial' => $trial], 201);
    }

    public function end(Request $request, GameSession $session): JsonResponse
    {
        abort_unless($session->child_id === $request->user()->id, 403);
        abort_if($session->status !== 'in_progress', 409, 'Session is not in progress.');

        $summary = $request->validate([
            'score' => ['nullable', 'integer', 'min:0'],
            'max_score' => ['nullable', 'integer', 'min:0'],
            'stars' => ['nullable', 'integer', 'min:0', 'max:3'],
            'result_payload' => ['nullable', 'array'],
        ]);

        $trials = $session->trials()->orderBy('trial_number')->get();

        if ($trials->isEmpty()) {
            $session->update([
                'ended_at' => now(),
                'status' => 'abandoned',
                'duration_sec' => now()->diffInSeconds($session->started_at),
                'result_payload' => $summary['result_payload'] ?? null,
            ]);

            return response()->json(['message' => 'Session ended with no trials.', 'session' => $session->fresh()]);
        }

        $totalTrials = $trials->count();
        $correctCount = $trials->where('correct', true)->count();
        $errorsCount = (int) $trials->sum('errors');
        $missedCount = (int) $trials->sum('missed_targets');
        $accuracy = round(($correctCount / $totalTrials) * 100, 2);
        $avgRT = round($trials->avg('reaction_time_ms'), 2);
        $duration = (int) $trials->sum('duration_sec');
        $score = (int) ($summary['score'] ?? $correctCount);
        $maxScore = (int) ($summary['max_score'] ?? $totalTrials);
        $stars = (int) ($summary['stars'] ?? $this->calculateStars($accuracy));

        DB::transaction(function () use ($session, $totalTrials, $correctCount, $errorsCount, $missedCount, $accuracy, $avgRT, $duration, $score, $maxScore, $stars, $summary) {
            $session->update([
                'ended_at' => now(),
                'duration_sec' => $duration,
                'trials_count' => $totalTrials,
                'correct_count' => $correctCount,
                'errors_count' => $errorsCount,
                'missed_count' => $missedCount,
                'accuracy' => $accuracy,
                'avg_reaction_time_ms' => $avgRT,
                'score' => $score,
                'max_score' => $maxScore,
                'stars' => $stars,
                'result_payload' => $summary['result_payload'] ?? null,
                'status' => 'completed',
            ]);
        });

        $prediction = $this->runPrediction($session, $trials);

        return response()->json([
            'message' => 'Session completed.',
            'session' => $session->fresh(),
            'prediction' => $this->formatPrediction($prediction),
        ]);
    }

    private function runPrediction(GameSession $session, $trials): ?VisualPrediction
    {
        $payload = $trials->map(fn (SessionTrial $trial) => [
            'User_ID' => (int) $session->child_id,
            'Trial_ID' => (int) $trial->trial_number,
            'Task_Type' => $this->normalizeTaskType($trial->task_type),
            'Stimulus_Count' => (int) $trial->stimulus_count,
            'Difficulty_Level' => $trial->difficulty_level,
            'Target_Type' => $this->normalizeTargetType($trial->target_type),
            'Reaction_Time_ms' => (int) $trial->reaction_time_ms,
            'Correct' => $trial->correct ? 1 : 0,
            'Errors' => (int) $trial->errors,
            'Missed_Targets' => (int) $trial->missed_targets,
            'Session_Duration_sec' => (int) $trial->duration_sec,
        ])->values()->toArray();

        try {
            $result = $this->ml->plan($payload);
        } catch (Throwable $e) {
            report($e);
            $result = $this->ml->fallbackPlan($payload);
        }

        $prediction = VisualPrediction::create([
            'child_id' => $session->child_id,
            'session_id' => $session->id,
            'status' => $result['status'],
            'label' => $result['label'],
            'confidence' => $result['confidence'],
            'prob_normal' => $result['probabilities']['Normal'] ?? 0,
            'prob_disorder' => $result['probabilities']['Visual_Perception_Disorder'] ?? 0,
            'weak_skills' => $result['weak_skills'] ?? [],
            'training_plan' => $result['training_plan'] ?? [],
            'trials_count' => $result['trials_count'] ?? $trials->count(),
            'model_version' => ! empty($result['fallback']) ? 'fallback' : ($result['model_version'] ?? 'v1.0.0'),
        ]);

        if ($prediction->status === 'visual_disorder') {
            $child = $session->child;
            AppNotification::create([
                'user_id' => $child->parent_id,
                'child_id' => $child->id,
                'type' => 'visual_disorder_alert',
                'title' => 'Attention needed for ' . $child->name,
                'body' => 'Recent gameplay suggests possible visual perception difficulties. We recommend consulting a specialist.',
                'data' => [
                    'child_id' => $child->id,
                    'prediction_id' => $prediction->id,
                    'confidence' => $prediction->confidence,
                ],
            ]);
        }

        return $prediction;
    }

    private function formatPrediction(?VisualPrediction $prediction): ?array
    {
        if (! $prediction) {
            return null;
        }

        return [
            'id' => $prediction->id,
            'session_id' => $prediction->session_id,
            'child_id' => $prediction->child_id,
            'status' => $prediction->status === 'visual_disorder' ? 'disorder' : 'normal',
            'label' => $prediction->label,
            'confidence' => $prediction->confidence,
            'prob_normal' => $prediction->prob_normal,
            'prob_disorder' => $prediction->prob_disorder,
            'weak_skills' => $prediction->weak_skills,
            'training_plan' => $prediction->training_plan,
            'trials_count' => $prediction->trials_count,
            'model_version' => $prediction->model_version,
            'created_at' => $prediction->created_at,
            'updated_at' => $prediction->updated_at,
        ];
    }

    public function history(Request $request): JsonResponse
    {
        $sessions = $request->user()
            ->sessions()
            ->with('game:id,slug,name,icon_url,asset_type,task_type,target_type')
            ->latest()
            ->paginate(20);

        return response()->json($sessions);
    }

    public function show(Request $request, GameSession $session): JsonResponse
    {
        abort_unless($session->child_id === $request->user()->id, 403);

        $session->load(['game', 'trials', 'prediction']);

        return response()->json(['session' => $session]);
    }

    private function calculateStars(float $accuracy): int
    {
        return match (true) {
            $accuracy >= 90 => 3,
            $accuracy >= 70 => 2,
            $accuracy >= 50 => 1,
            default => 0,
        };
    }

    private function normalizeTaskType(string $taskType): string
    {
        $normalized = strtolower(trim($taskType));

        return match ($normalized) {
            'tracking' => 'Tracking',
            'discrimination', 'visual', 'find-items', 'find items' => 'Discrimination',
            'matching', 'animal-match', 'animal match', 'shape-match', 'shape match' => 'Matching',
            'orientation', 'sequence', 'sequence-game', 'sequence game' => 'Orientation',
            default => 'Discrimination',
        };
    }

    private function normalizeTargetType(string $targetType): string
    {
        $normalized = strtolower(trim($targetType));

        return match ($normalized) {
            'direction', 'sequence' => 'Direction',
            'color' => 'Color',
            'position', 'object', 'find-item', 'find items' => 'Position',
            'shape', 'animal', 'pattern', 'icon' => 'Shape',
            default => 'Shape',
        };
    }
}

<?php

namespace App\Http\Controllers\Api\ParentApi;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\GameSession;
use App\Models\VisualPrediction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, Child $child): JsonResponse
    {
        abort_unless($child->parent_id === $request->user()->id, 403);

        $totalSessions = $child->sessions()->count();
        $completedSessionsQuery = $child->sessions()->where('status', 'completed');
        $completedSessions = (clone $completedSessionsQuery)->count();
        $totalTrials = (int) (clone $completedSessionsQuery)->sum('trials_count');
        $avgAccuracy = round((float) ((clone $completedSessionsQuery)->avg('accuracy') ?? 0), 2);

        $latestPrediction = $child->predictions()->latest()->first();
        $latestCompletedSession = (clone $completedSessionsQuery)
            ->with(['game:id,slug,name,icon_url', 'prediction'])
            ->latest()
            ->first();
        $recentCompletedSessions = (clone $completedSessionsQuery)
            ->with(['game:id,slug,name,icon_url', 'prediction'])
            ->latest()
            ->limit(5)
            ->get();
        $assessment = $this->buildAssessment(
            $avgAccuracy,
            $recentCompletedSessions,
            $latestCompletedSession,
            $latestPrediction,
        );

        $weeklyActivity = GameSession::query()
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("strftime('%Y-%m-%d', created_at) as day, COUNT(*) as sessions, SUM(duration_sec) as seconds")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $chart = $days->map(function (string $day) use ($weeklyActivity) {
            $row = $weeklyActivity->firstWhere('day', $day);
            return [
                'day' => $day,
                'sessions' => $row ? (int) $row->sessions : 0,
                'minutes' => $row ? (int) round($row->seconds / 60) : 0,
            ];
        });

        $topGames = GameSession::query()
            ->where('child_id', $child->id)
            ->where('status', 'completed')
            ->selectRaw('game_id, COUNT(*) as plays, AVG(accuracy) as avg_accuracy, SUM(score) as total_score, SUM(max_score) as total_max_score')
            ->groupBy('game_id')
            ->orderByDesc('plays')
            ->with('game:id,slug,name,icon_url')
            ->limit(5)
            ->get()
            ->map(fn (GameSession $session) => [
                'game_id' => $session->game_id,
                'plays' => (int) $session->plays,
                'avg_accuracy' => round((float) $session->avg_accuracy, 2),
                'total_score' => (int) $session->total_score,
                'total_max_score' => (int) $session->total_max_score,
                'score_text' => (int) $session->total_max_score > 0
                    ? ((int) $session->total_score . '/' . (int) $session->total_max_score)
                    : null,
                'game' => $session->game,
            ]);

        $isEmpty = $totalSessions === 0;
        $recentSessions = $recentCompletedSessions->map(fn (GameSession $session) => $this->formatSession($session));

        return response()->json([
            'child' => $child,
            'summary' => [
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'total_trials' => $totalTrials,
                'avg_accuracy' => $avgAccuracy,
            ],
            'stats' => [
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'total_trials' => $totalTrials,
                'avg_accuracy' => $avgAccuracy,
            ],
            'latest_prediction' => $this->formatPrediction($latestPrediction),
            'prediction' => $assessment,
            'dashboard_assessment' => $assessment,
            'weekly_activity' => $chart,
            'chart' => $chart,
            'top_games' => $topGames,
            'recent_sessions' => $recentSessions,
            'recent_history' => $recentSessions,
            'is_empty' => $isEmpty,
            'empty_state' => $isEmpty
                ? 'No gameplay data yet. Start a child game session to see dashboard insights.'
                : null,
        ]);
    }

    public function predictions(Request $request, Child $child): JsonResponse
    {
        abort_unless($child->parent_id === $request->user()->id, 403);

        $predictions = $child->predictions()
            ->with('session.game:id,slug,name,icon_url')
            ->latest()
            ->paginate(20);
        $predictions->setCollection($predictions->getCollection()->map(fn (VisualPrediction $prediction) => $this->formatPrediction($prediction)));

        return response()->json($predictions);
    }

    public function sessions(Request $request, Child $child): JsonResponse
    {
        abort_unless($child->parent_id === $request->user()->id, 403);

        $sessions = $child->sessions()
            ->with(['game:id,slug,name,icon_url', 'prediction'])
            ->latest()
            ->paginate(20);
        $sessions->setCollection($sessions->getCollection()->map(fn (GameSession $session) => $this->formatSession($session)));

        return response()->json($sessions);
    }

    private function formatPrediction(?VisualPrediction $prediction, bool $includeSession = true): ?array
    {
        if (! $prediction) {
            return null;
        }

        $payload = [
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

        if ($includeSession && $prediction->relationLoaded('session') && $prediction->session) {
            $payload['session_summary'] = $this->formatSession($prediction->session, false);
        }

        return $payload;
    }

    private function formatSession(GameSession $session, bool $includePrediction = true): array
    {
        $game = $session->game;
        $score = (int) ($session->score ?? 0);
        $maxScore = (int) ($session->max_score ?? 0);

        $payload = [
            'id' => $session->id,
            'child_id' => $session->child_id,
            'game_id' => $session->game_id,
            'game_name' => $game?->name,
            'game_slug' => $game?->slug,
            'score' => $score,
            'max_score' => $maxScore,
            'score_text' => $maxScore > 0 ? "{$score}/{$maxScore}" : (string) $score,
            'accuracy' => round((float) ($session->accuracy ?? 0), 2),
            'accuracy_text' => number_format((float) ($session->accuracy ?? 0), 2) . '%',
            'trials_count' => (int) ($session->trials_count ?? 0),
            'correct_count' => (int) ($session->correct_count ?? 0),
            'errors_count' => (int) ($session->errors_count ?? 0),
            'missed_count' => (int) ($session->missed_count ?? 0),
            'stars' => (int) ($session->stars ?? 0),
            'status' => $session->status,
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
            'game' => $game,
        ];

        if ($includePrediction && $session->relationLoaded('prediction')) {
            $payload['prediction'] = $this->formatPrediction($session->prediction, false);
        }

        return $payload;
    }

    private function buildAssessment(
        float $avgAccuracy,
        Collection $recentSessions,
        ?GameSession $latestSession,
        ?VisualPrediction $latestPrediction
    ): ?array {
        if ($recentSessions->isEmpty()) {
            return null;
        }

        $recentAvgAccuracy = round((float) $recentSessions->avg('accuracy'), 2);
        $latestAccuracy = round((float) ($latestSession?->accuracy ?? 0), 2);

        $severity = match (true) {
            $avgAccuracy < 60 || $latestAccuracy < 50 || $recentAvgAccuracy < 60 => 'weak',
            $avgAccuracy < 80 || $latestAccuracy < 70 || $recentAvgAccuracy < 75 => 'monitor',
            default => 'good',
        };

        $status = $severity === 'good' ? 'normal' : 'disorder';
        $title = match ($severity) {
            'weak' => 'Possible Visual Perception Weakness',
            'monitor' => 'Keep Monitoring',
            default => 'Looking Good!',
        };
        $message = match ($severity) {
            'weak' => 'Low accuracy may indicate visual perception weakness. We recommend visiting a doctor or specialist for a proper evaluation.',
            'monitor' => 'Performance is mixed. Keep tracking the next sessions closely.',
            default => 'Recent gameplay is showing healthy visual development patterns.',
        };

        return [
            'status' => $status,
            'severity' => $severity,
            'title' => $title,
            'label' => match ($severity) {
                'weak' => 'Possible Visual Perception Weakness',
                'monitor' => 'Monitor Closely',
                default => 'Normal Pattern',
            },
            'message' => $message,
            'source' => 'dashboard_summary',
            'avg_accuracy' => $avgAccuracy,
            'recent_avg_accuracy' => $recentAvgAccuracy,
            'latest_accuracy' => $latestAccuracy,
            'latest_game_name' => $latestSession?->game?->name,
            'latest_score' => (int) ($latestSession?->score ?? 0),
            'latest_max_score' => (int) ($latestSession?->max_score ?? 0),
            'latest_score_text' => $latestSession && (int) $latestSession->max_score > 0
                ? ((int) $latestSession->score . '/' . (int) $latestSession->max_score)
                : null,
            'confidence' => $latestPrediction?->confidence,
            'prob_normal' => $latestPrediction?->prob_normal,
            'prob_disorder' => $latestPrediction?->prob_disorder,
            'created_at' => $latestPrediction?->created_at ?? $latestSession?->created_at,
            'updated_at' => $latestPrediction?->updated_at ?? $latestSession?->updated_at,
        ];
    }
}

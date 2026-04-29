<?php

namespace App\Http\Controllers\Api\ParentApi;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\GameSession;
use App\Models\VisualPrediction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, Child $child): JsonResponse
    {
        abort_unless($child->parent_id === $request->user()->id, 403);

        $totalSessions = $child->sessions()->count();
        $completedSessions = $child->sessions()->where('status', 'completed')->count();
        $totalTrials = $child->sessions()->sum('trials_count');
        $avgAccuracy = round((float) $child->sessions()->avg('accuracy'), 2);

        $latestPrediction = $child->predictions()->latest()->first();

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
            ->selectRaw('game_id, COUNT(*) as plays, AVG(accuracy) as avg_accuracy')
            ->groupBy('game_id')
            ->orderByDesc('plays')
            ->with('game:id,slug,name,icon_url')
            ->limit(5)
            ->get();

        $recentSessions = $child->sessions()
            ->with('game:id,slug,name,icon_url')
            ->latest()
            ->limit(5)
            ->get();

        $isEmpty = $totalSessions === 0;

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
            'prediction' => $this->formatPrediction($latestPrediction),
            'weekly_activity' => $chart,
            'chart' => $chart,
            'top_games' => $topGames,
            'recent_sessions' => $recentSessions,
            'is_empty' => $isEmpty,
            'empty_state' => $isEmpty
                ? 'No gameplay data yet. Start a child game session to see dashboard insights.'
                : null,
        ]);
    }

    public function predictions(Request $request, Child $child): JsonResponse
    {
        abort_unless($child->parent_id === $request->user()->id, 403);

        $predictions = $child->predictions()->latest()->paginate(20);
        $predictions->setCollection($predictions->getCollection()->map(fn (VisualPrediction $prediction) => $this->formatPrediction($prediction)));

        return response()->json($predictions);
    }

    public function sessions(Request $request, Child $child): JsonResponse
    {
        abort_unless($child->parent_id === $request->user()->id, 403);

        $sessions = $child->sessions()
            ->with('game:id,slug,name,icon_url')
            ->latest()
            ->paginate(20);

        return response()->json($sessions);
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
}

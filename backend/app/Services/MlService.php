<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MlService
{
    private string $baseUrl;
    private int $timeout;
    private string $mode;

    private const TASK_TO_SKILL = [
        'Tracking' => 'Visual Tracking',
        'Discrimination' => 'Visual Discrimination',
        'Matching' => 'Visual Matching',
        'Orientation' => 'Spatial Orientation',
    ];

    private const SKILL_TO_EXERCISES = [
        'Visual Tracking' => [
            'Maze navigation (easy)',
            'Follow-the-dot animation',
            'Moving shape tracker',
        ],
        'Visual Discrimination' => [
            'Spot the difference',
            'Odd-one-out shapes',
            'Color-shade matching',
        ],
        'Visual Matching' => [
            'Shape matcher game',
            'Pair matching puzzle',
            'Pattern completion',
        ],
        'Spatial Orientation' => [
            'Rotate the shape',
            'Mirror image matching',
            'Direction arrows game',
        ],
        'General Visual Skill' => [
            'Memory cards',
            'Picture puzzle (easy)',
        ],
    ];

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ml.url', 'http://127.0.0.1:8001'), '/');
        $this->timeout = (int) config('services.ml.timeout', 10);
        $this->mode = (string) config('services.ml.mode', 'auto');
    }

    public function health(): array
    {
        if ($this->shouldUseLocalFallback()) {
            return [
                'status' => 'ok',
                'service' => 'KidZoo Local ML Fallback',
                'mode' => 'local',
            ];
        }

        $response = Http::timeout($this->timeout)->get("{$this->baseUrl}/health");
        $response->throw();
        return $response->json();
    }

    public function predict(array $trials): array
    {
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->post("{$this->baseUrl}/predict", ['trials' => $trials]);

        if ($response->failed()) {
            Log::warning('ML /predict failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('ML service prediction failed: ' . $response->body());
        }

        return $response->json();
    }

    public function plan(array $trials, float $scoreThreshold = 60.0, int $days = 7): array
    {
        if ($this->shouldUseLocalFallback()) {
            return $this->localPlan($trials, $scoreThreshold, $days);
        }

        $n8nPlan = $this->planViaN8n($trials, $scoreThreshold, $days);

        if ($n8nPlan !== null) {
            return $n8nPlan;
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->post("{$this->baseUrl}/plan", [
                'trials' => $trials,
                'score_threshold' => $scoreThreshold,
                'days' => $days,
            ]);

        if ($response->failed()) {
            Log::warning('ML /plan failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('ML service plan failed: ' . $response->body());
        }

        return $this->normalizePlanResponse($response->json(), count($trials));
    }

    public function fallbackPlan(array $trials): array
    {
        return $this->localPlan($trials, 60.0, 7, true);
    }

    private function planViaN8n(array $trials, float $scoreThreshold, int $days): ?array
    {
        $urls = array_values(array_filter([
            config('services.n8n.ai_webhook_url'),
            config('services.n8n.ai_test_webhook_url'),
        ]));

        if ($urls === []) {
            return null;
        }

        foreach ($urls as $url) {
            try {
                $request = Http::timeout((int) config('services.n8n.ai_timeout', 15))
                    ->acceptJson()
                    ->asJson();

                $token = config('services.n8n.ai_token');

                if ($token) {
                    $request = $request->withToken($token);
                }

                $response = $request->post($url, [
                    'trials' => $trials,
                    'score_threshold' => $scoreThreshold,
                    'days' => $days,
                ]);

                if (! $response->successful()) {
                    Log::warning('n8n AI webhook returned non-success response', [
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                return $this->normalizePlanResponse($response->json(), count($trials));
            } catch (\Throwable $e) {
                Log::warning('n8n AI webhook request failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function normalizePlanResponse(array $result, int $trialCount): array
    {
        $status = strtolower((string) ($result['status'] ?? $result['prediction_status'] ?? 'normal'));
        $status = match ($status) {
            'disorder', 'visual_disorder', 'visual-perception-disorder', 'visual_perception_disorder' => 'visual_disorder',
            default => 'normal',
        };

        $probabilities = $result['probabilities'] ?? [];

        $probNormal = (float) (
            $result['prob_normal']
            ?? $probabilities['Normal']
            ?? $probabilities['normal']
            ?? ($status === 'normal' ? 1 : 0)
        );

        $probDisorder = (float) (
            $result['prob_disorder']
            ?? $probabilities['Visual_Perception_Disorder']
            ?? $probabilities['visual_disorder']
            ?? $probabilities['disorder']
            ?? ($status === 'visual_disorder' ? 1 : 0)
        );

        return [
            'status' => $status,
            'label' => (string) ($result['label'] ?? ($status === 'visual_disorder' ? 'Visual Perception Disorder Detected' : 'Normal Child')),
            'confidence' => (float) ($result['confidence'] ?? max($probNormal, $probDisorder)),
            'probabilities' => [
                'Normal' => $probNormal,
                'Visual_Perception_Disorder' => $probDisorder,
            ],
            'weak_skills' => $result['weak_skills'] ?? [],
            'training_plan' => $result['training_plan'] ?? [],
            'trials_count' => (int) ($result['trials_count'] ?? $trialCount),
            'model_version' => (string) ($result['model_version'] ?? 'n8n'),
            'raw' => $result,
        ];
    }

    private function shouldUseLocalFallback(): bool
    {
        return in_array($this->mode, ['local', 'fallback_only'], true);
    }

    private function localPlan(array $trials, float $scoreThreshold, int $days, bool $fallback = false): array
    {
        $trialCount = count($trials);

        if ($trialCount === 0) {
            return [
                'status' => 'normal',
                'label' => 'Pending Review',
                'confidence' => 0,
                'probabilities' => [
                    'Normal' => 0,
                    'Visual_Perception_Disorder' => 0,
                ],
                'weak_skills' => [],
                'training_plan' => [],
                'trials_count' => 0,
                'model_version' => 'local-rule-v1',
                'fallback' => true,
            ];
        }

        $correctCount = 0;
        $totalErrors = 0;
        $totalMissed = 0;
        $totalReactionTime = 0;
        $weakSkillsCounter = [];

        foreach ($trials as $trial) {
            $correct = (int) ($trial['Correct'] ?? $trial['correct'] ?? 0);
            $errors = (int) ($trial['Errors'] ?? $trial['errors'] ?? 0);
            $missed = (int) ($trial['Missed_Targets'] ?? $trial['missed_targets'] ?? 0);
            $reactionTime = (int) ($trial['Reaction_Time_ms'] ?? $trial['reaction_time_ms'] ?? 0);
            $taskType = (string) ($trial['Task_Type'] ?? $trial['task_type'] ?? '');

            $correctCount += $correct === 1 ? 1 : 0;
            $totalErrors += $errors;
            $totalMissed += $missed;
            $totalReactionTime += $reactionTime;

            $score = $this->computeTrialScore($correct, $errors, $missed);

            if ($score < $scoreThreshold) {
                $skill = self::TASK_TO_SKILL[$taskType] ?? 'General Visual Skill';
                $weakSkillsCounter[$skill] = ($weakSkillsCounter[$skill] ?? 0) + 1;
            }
        }

        $accuracy = $trialCount > 0 ? ($correctCount / $trialCount) * 100 : 0;
        $avgReaction = $trialCount > 0 ? $totalReactionTime / $trialCount : 0;

        $isDisorder = $accuracy < 60
            || $totalErrors >= max(3, (int) ceil($trialCount / 2))
            || $totalMissed >= max(2, (int) ceil($trialCount / 3))
            || $avgReaction > 2500;

        $confidence = $isDisorder
            ? min(0.97, max(0.65, 1 - ($accuracy / 100) + min($totalErrors * 0.03, 0.15)))
            : min(0.98, max(0.55, $accuracy / 100));

        $probDisorder = $isDisorder ? $confidence : max(0.02, 1 - $confidence);
        $probNormal = $isDisorder ? max(0.02, 1 - $confidence) : $confidence;

        return [
            'status' => $isDisorder ? 'visual_disorder' : 'normal',
            'label' => $isDisorder ? 'Visual Perception Disorder Detected' : 'Normal Child',
            'confidence' => round($confidence, 4),
            'probabilities' => [
                'Normal' => round($probNormal, 4),
                'Visual_Perception_Disorder' => round($probDisorder, 4),
            ],
            'weak_skills' => $weakSkillsCounter,
            'training_plan' => $this->buildTrainingPlan($weakSkillsCounter, $days),
            'trials_count' => $trialCount,
            'model_version' => 'local-rule-v1',
            'fallback' => $fallback,
        ];
    }

    private function computeTrialScore(int $correct, int $errors, int $missed): float
    {
        if ($correct === 1 && $errors === 0 && $missed === 0) {
            return 100.0;
        }

        $penalty = (20 * $errors) + (25 * $missed);
        $base = $correct === 1 ? 100 : 40;

        return max(0.0, $base - $penalty);
    }

    private function buildTrainingPlan(array $weakSkills, int $days): array
    {
        if ($weakSkills === []) {
            return [
                'Keep playing a mix of matching and tracking games.',
                'Increase difficulty gradually to maintain progress.',
            ];
        }

        $plan = [];
        arsort($weakSkills);
        $skills = array_keys($weakSkills);

        for ($day = 1; $day <= $days; $day++) {
            foreach ($skills as $skill) {
                $exercises = self::SKILL_TO_EXERCISES[$skill] ?? self::SKILL_TO_EXERCISES['General Visual Skill'];
                $plan[] = "Day {$day}: " . $exercises[($day - 1) % count($exercises)];
            }
        }

        return $plan;
    }
}

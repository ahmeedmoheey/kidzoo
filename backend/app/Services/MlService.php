<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MlService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ml.url', 'http://127.0.0.1:8001'), '/');
        $this->timeout = (int) config('services.ml.timeout', 10);
    }

    public function health(): array
    {
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
        $trialCount = count($trials);

        return [
            'status' => 'normal',
            'label' => 'Pending Review',
            'confidence' => 0,
            'probabilities' => [
                'Normal' => 0,
                'Visual_Perception_Disorder' => 0,
            ],
            'weak_skills' => [],
            'training_plan' => [
                'ML service is currently unavailable.',
                'Please try again later to generate an AI-based training plan.',
            ],
            'trials_count' => $trialCount,
            'fallback' => true,
        ];
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
}

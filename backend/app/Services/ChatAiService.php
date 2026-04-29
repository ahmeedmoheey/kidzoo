<?php

namespace App\Services;

use App\Models\ChatbotMessage;
use App\Models\Child;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatAiService
{
    public ?string $lastFailureCode = null;

    public function generateReply(User|Child $actor, string $message): ?string
    {
        $this->lastFailureCode = null;

        return match ($this->provider()) {
            'openrouter' => $this->generateViaOpenRouter($actor, $message),
            'openai' => $this->generateViaOpenAi($actor, $message),
            default => $this->generateAuto($actor, $message),
        };
    }

    private function generateAuto(User|Child $actor, string $message): ?string
    {
        $openRouterKey = (string) config('services.openrouter.api_key');

        if ($openRouterKey !== '') {
            $reply = $this->generateViaOpenRouter($actor, $message);

            if ($reply !== null) {
                return $reply;
            }
        }

        $openAiKey = (string) config('services.openai.api_key');

        if ($openAiKey !== '') {
            return $this->generateViaOpenAi($actor, $message);
        }

        $this->lastFailureCode = 'missing_api_key';

        return null;
    }

    private function generateViaOpenRouter(User|Child $actor, string $message): ?string
    {
        $apiKey = (string) config('services.openrouter.api_key');

        if ($apiKey === '') {
            $this->lastFailureCode = 'missing_api_key';

            return null;
        }

        $history = $this->buildHistory($actor);

        try {
            $request = Http::connectTimeout(5)
                ->timeout((int) config('services.openrouter.timeout', 12))
                ->acceptJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => config('app.name', 'KidZoo'),
                ]);

            if (app()->environment('local')) {
                $request = $request->withoutVerifying();
            }

            $response = $request->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => config('services.openrouter.model', 'openrouter/free'),
                'messages' => array_merge(
                    [[
                        'role' => 'system',
                        'content' => $this->systemPrompt($actor),
                    ]],
                    $history,
                    [[
                        'role' => 'user',
                        'content' => $message,
                    ]],
                ),
            ]);

            if (! $response->successful()) {
                $this->lastFailureCode = (string) ($response->json('error.code') ?? $response->status());

                Log::warning('OpenRouter chatbot request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $reply = trim((string) ($response->json('choices.0.message.content') ?? ''));

            return $reply !== '' ? $reply : null;
        } catch (\Throwable $e) {
            $this->lastFailureCode = 'request_exception';

            Log::warning('OpenRouter chatbot request exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function generateViaOpenAi(User|Child $actor, string $message): ?string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            $this->lastFailureCode = 'missing_api_key';

            return null;
        }

        $history = $this->buildHistory($actor);

        try {
            $request = Http::connectTimeout(5)
                ->timeout((int) config('services.openai.timeout', 12))
                ->acceptJson()
                ->withToken($apiKey);

            if (app()->environment('local')) {
                $request = $request->withoutVerifying();
            }

            $response = $request->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5-mini'),
                'input' => array_merge(
                    [[
                        'role' => 'system',
                        'content' => $this->systemPrompt($actor),
                    ]],
                    $history,
                    [[
                        'role' => 'user',
                        'content' => $message,
                    ]],
                ),
                'text' => [
                    'format' => [
                        'type' => 'text',
                    ],
                ],
            ]);

            if (! $response->successful()) {
                $this->lastFailureCode = (string) ($response->json('error.code') ?? $response->status());

                Log::warning('OpenAI chatbot request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $reply = trim((string) ($response->json('output_text') ?? ''));

            if ($reply !== '') {
                return $reply;
            }

            $output = $response->json('output.0.content.0.text');

            return is_string($output) && trim($output) !== '' ? trim($output) : null;
        } catch (\Throwable $e) {
            $this->lastFailureCode = 'request_exception';

            Log::warning('OpenAI chatbot request exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function provider(): string
    {
        return strtolower((string) config('services.chat_ai.provider', 'auto'));
    }

    private function buildHistory(User|Child $actor): array
    {
        /** @var Collection<int, ChatbotMessage> $messages */
        $messages = $actor->chatbotMessages()
            ->latest()
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        return $messages->map(function (ChatbotMessage $message) {
            return [
                'role' => $message->role === 'bot' ? 'assistant' : 'user',
                'content' => $message->message,
            ];
        })->all();
    }

    private function systemPrompt(User|Child $actor): string
    {
        $name = $actor->name;
        $role = $actor instanceof Child ? 'child' : 'parent';
        $childContext = $actor instanceof User ? $this->buildParentChildContext($actor) : 'No child dashboard context was supplied for this user.';

        return <<<PROMPT
You are KidZoo AI, a warm and helpful assistant for children and parents.
Current user name: {$name}
Current user type: {$role}
Parent/child data context:
{$childContext}

Rules:
- Reply in the same language as the user's message when possible.
- If the user writes in Arabic, reply in clear natural Arabic. Prefer simple Egyptian Arabic or easy Modern Standard Arabic.
- Be helpful on a wide range of everyday questions, but stay especially strong on child learning, attention, autism, ADHD, visual perception, and KidZoo games.
- For children, use short, friendly, safe language.
- For parents, give clear, practical guidance in simple language.
- If the current user is a parent and asks about their child progress, use the provided child data context.
- Never claim to diagnose a medical condition.
- Never say a child definitely has a disease or definitely does not have a disease based only on app data.
- When discussing child results, use wording like: indicators, possible concern, needs follow-up, or data is not enough.
- If the parent asks whether the child is ill or has a disorder, explain that the app can only show indicators or risk patterns, not a final diagnosis.
- If a question is medical or high risk, suggest consulting a qualified professional.
- Keep answers concise but real, natural, and useful.
PROMPT;
    }

    private function buildParentChildContext(User $parent): string
    {
        $children = $parent->children()
            ->with([
                'predictions' => fn ($query) => $query->latest()->limit(1),
                'sessions' => fn ($query) => $query->latest()->limit(1),
            ])
            ->withCount('sessions')
            ->get();

        if ($children->isEmpty()) {
            return 'This parent has no children registered yet.';
        }

        return $children->map(function (Child $child) {
            $latestPrediction = $child->predictions->first();
            $latestSession = $child->sessions->first();
            $predictionLabel = $latestPrediction?->label ?? 'No prediction yet';
            $predictionStatus = $latestPrediction?->status ?? 'no_data';
            $predictionConfidence = $latestPrediction?->confidence !== null
                ? number_format((float) $latestPrediction->confidence, 2)
                : 'n/a';
            $weakSkills = $latestPrediction && is_array($latestPrediction->weak_skills) && $latestPrediction->weak_skills !== []
                ? implode(', ', $latestPrediction->weak_skills)
                : 'none';
            $trainingPlan = $latestPrediction && is_array($latestPrediction->training_plan) && $latestPrediction->training_plan !== []
                ? implode('; ', $latestPrediction->training_plan)
                : 'none';
            $latestAccuracy = $latestSession?->accuracy !== null
                ? number_format((float) $latestSession->accuracy, 2)
                : 'n/a';
            $latestReaction = $latestSession?->avg_reaction_time_ms !== null
                ? number_format((float) $latestSession->avg_reaction_time_ms, 2)
                : 'n/a';
            $latestTrials = $latestSession?->trials_count ?? 'n/a';
            $latestSessionStatus = $latestSession?->status ?? 'no_session';

            return implode(' | ', [
                "Child: {$child->name}",
                "Age: " . ($child->age ?? 'n/a'),
                "Gender: " . ($child->gender ?? 'n/a'),
                "Sessions: {$child->sessions_count}",
                "Latest session status: {$latestSessionStatus}",
                "Latest session trials: {$latestTrials}",
                "Latest accuracy: {$latestAccuracy}",
                "Latest avg reaction ms: {$latestReaction}",
                "Latest prediction: {$predictionLabel}",
                "Prediction status: {$predictionStatus}",
                "Confidence: {$predictionConfidence}",
                "Weak skills: {$weakSkills}",
                "Training plan: {$trainingPlan}",
            ]);
        })->implode("\n");
    }
}

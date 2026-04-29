<?php

namespace App\Http\Controllers\Api\ChildApi;

use App\Http\Controllers\Controller;
use App\Models\ChatbotMessage;
use App\Models\Child;
use App\Models\User;
use App\Services\ChatAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private const FALLBACK_REPLIES = [
        'أنا هنا للمساعدة في نمو الطفل، والانتباه، والألعاب التعليمية، وإرشاد الأهل. اكتب سؤالك بشكل أوضح وسأحاول مساعدتك.',
        'أستطيع المساعدة في أسئلة ADHD، والتوحد، والتركيز، والإدراك البصري، وكيف تساعد الألعاب الطفل. ما الذي تريد معرفته؟',
        'اكتب سؤالك بالعربية أو الإنجليزية وسأحاول إعطاءك إجابة بسيطة ومفيدة.',
    ];

    public function __construct(private readonly ChatAiService $chatAi)
    {
    }

    public function history(Request $request): JsonResponse
    {
        $actor = $request->user();

        $messages = $actor
            ->chatbotMessages()
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        if ($messages->isEmpty()) {
            $welcome = "مرحبًا {$actor->name}! أنا هنا للدردشة والمساعدة.";

            return response()->json([
                'messages' => [[
                    'id' => null,
                    'user_id' => $actor instanceof User ? $actor->id : null,
                    'child_id' => $actor instanceof Child ? $actor->id : null,
                    'role' => 'bot',
                    'message' => $welcome,
                    'created_at' => null,
                    'updated_at' => null,
                ]],
                'reply' => $welcome,
                'has_history' => false,
            ]);
        }

        return response()->json([
            'messages' => $messages,
            'reply' => optional($messages->where('role', 'bot')->last())->message,
            'has_history' => true,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $actor = $request->user();
        $userMsg = $this->storeMessage($actor, 'user', $data['message']);
        $reply = $this->generateReply($actor, $data['message'], $actor->name);
        $botMsg = $this->storeMessage($actor, 'bot', $reply);

        return response()->json([
            'message' => $botMsg,
            'reply' => $botMsg->message,
            'messages' => [
                $userMsg,
                $botMsg,
            ],
        ]);
    }

    private function generateReply(User|Child $actor, string $input, string $displayName): string
    {
        $aiReply = $this->chatAi->generateReply($actor, $input);

        if ($aiReply && ! $this->isGenericReply($aiReply)) {
            return $aiReply;
        }

        $n8nReply = $this->generateReplyViaN8n($input, $displayName);

        if ($n8nReply && ! $this->isGenericReply($n8nReply)) {
            return $n8nReply;
        }

        $localReply = $this->generateSmartLocalReply($input, $displayName);

        if ($localReply !== null) {
            return $localReply;
        }

        if ($this->chatAi->lastFailureCode === 'insufficient_quota') {
            return 'خدمة الذكاء الاصطناعي متصلة، لكن الرصيد الحالي غير كافٍ. فعّل الرصيد أو استخدم مفتاحًا آخر للحصول على إجابات أذكى للأسئلة العامة.';
        }

        if ($this->chatAi->lastFailureCode === 'request_exception') {
            return 'حصلت مشكلة مؤقتة في الاتصال بخدمة الذكاء الاصطناعي. حاول مرة أخرى بعد لحظة.';
        }

        return self::FALLBACK_REPLIES[array_rand(self::FALLBACK_REPLIES)];
    }

    private function generateSmartLocalReply(string $input, string $name): ?string
    {
        $message = trim($input);

        if ($message === '') {
            return 'اكتب سؤالك وسأحاول مساعدتك.';
        }

        $lower = mb_strtolower($message);

        foreach ($this->smartReplyMap($name) as $keywords => $reply) {
            foreach (explode('|', $keywords) as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $reply;
                }
            }
        }

        if (
            str_contains($lower, 'mo salah')
            || str_contains($lower, 'mohamed salah')
            || str_contains($lower, 'mohammad salah')
            || str_contains($lower, 'محمد صلاح')
        ) {
            return 'محمد صلاح لاعب كرة قدم مصري مشهور جدًا. اشتهر بشكل كبير مع ليفربول ويُعد من أبرز اللاعبين العرب في العالم.';
        }

        if (
            str_starts_with($lower, 'who is ')
            || str_contains($lower, 'مين ')
            || str_contains($lower, 'من هو')
            || str_contains($lower, 'من هي')
        ) {
            return 'أستطيع الإجابة بشكل أفضل عندما تكون خدمة الذكاء الاصطناعي متاحة بشكل كامل. إذا أردت، اكتب سؤالك بتفصيل أكبر وسأحاول مساعدتك بما هو متوفر.';
        }

        if (
            str_contains($lower, '؟')
            || str_contains($lower, '?')
            || str_contains($lower, 'ايه')
            || str_contains($lower, 'إيه')
            || str_contains($lower, 'ما هو')
            || str_contains($lower, 'ما هي')
            || str_contains($lower, 'ازاي')
            || str_contains($lower, 'كيف')
            || str_contains($lower, 'ليه')
            || str_contains($lower, 'لماذا')
        ) {
            return 'فهمت إنك تسأل سؤالًا. أستطيع المساعدة في التوحد، وADHD، والانتباه، والإدراك البصري، وتطور الطفل، وشرح نتائج الألعاب. اكتب سؤالك بشكل أوضح قليلًا وسأساعدك.';
        }

        return null;
    }

    private function smartReplyMap(string $name): array
    {
        return [
            'hi|hello|hey|السلام عليكم|سلام|اهلا|أهلا|مرحبا|ازيك|عامل ايه' => "مرحبًا {$name}! أستطيع مساعدتك في أسئلة تطور الطفل، والانتباه، والألعاب التعليمية، وإرشاد الأهل. ماذا تريد أن تسأل؟",
            'how are you|كيف حالك|اخبارك|أخبارك|عامل ايه|عامل ايه؟' => "أنا بخير يا {$name}. أنا هنا لمساعدتك في أسئلة الطفل والتعلم والتطور. اسألني ما تريد.",
            'adhd|ما هو adhd|ايه adhd|إيه adhd|فرط الحركة|تشتت الانتباه|نقص الانتباه' => 'ADHD هو اضطراب قد يؤثر على الانتباه، وضبط الاندفاع، ومستوى النشاط. قد يواجه الطفل صعوبة في التركيز أو يكون شديد الحركة. التشخيص يحتاج مختصًا، لكن يمكنني شرح الأعراض بشكل مبسط إذا أردت.',
            'autism|autistic|التوحد|طفل توحدي|autism spectrum' => 'التوحد هو حالة نمائية قد تؤثر على التواصل، والتفاعل الاجتماعي، وطريقة استجابة الطفل للمؤثرات. تختلف الأعراض من طفل لآخر، والتقييم الأدق يكون عند مختص.',
            'visual perception|visual disorder|الإدراك البصري|الادراك البصري|perception' => 'الإدراك البصري هو الطريقة التي يفسر بها الدماغ ما تراه العين. بعض الأطفال قد يواجهون صعوبة في التتبع أو المطابقة أو تمييز الاتجاهات والأشكال.',
            'what can you do|بتعمل ايه|تقدر تساعدني في ايه|مساعدة|ساعدني|help' => 'أستطيع شرح ADHD، والتوحد، والانتباه، والإدراك البصري، وشرح نتائج التطبيق، واقتراح خطوات بسيطة ومفيدة للأهل.',
            'game|games|play|لعبة|العاب|ألعاب|العب' => 'ألعاب KidZoo تساعد في مهارات مثل المطابقة، والتتبع البصري، والانتباه، والإدراك. إذا أردت أشرح لك فائدة كل لعبة.',
            'score|accuracy|result|prediction|نتيجة|تحليل|تقييم|التشخيص' => 'التطبيق يحفظ نتائج الجلسات ويعرض آخر prediction في الداشبورد. إذا أردت، أشرح لك معنى النتائج بشكل بسيط.',
            'focus|attention|concentration|التركيز|الانتباه' => 'مهارات الانتباه يمكن تحسينها بأنشطة قصيرة ومنظمة، مع تقليل المشتتات، وتعليمات واضحة، وتدريب منتظم.',
            'bye|goodbye|مع السلامه|مع السلامة|باي|bye bye' => "مع السلامة يا {$name}. لو احتجت أي مساعدة لاحقًا سأكون هنا.",
        ];
    }

    private function isGenericReply(string $reply): bool
    {
        $genericReplies = [
            'Great job',
            'Tell me more',
            'That sounds interesting',
            'You are doing well',
            'Let us keep learning',
        ];

        foreach ($genericReplies as $genericReply) {
            if (str_contains($reply, $genericReply)) {
                return true;
            }
        }

        return false;
    }

    private function generateReplyViaN8n(string $input, string $displayName): ?string
    {
        $urls = array_values(array_filter([
            config('services.n8n.chat_webhook_url'),
            config('services.n8n.chat_test_webhook_url'),
        ]));

        if ($urls === []) {
            return null;
        }

        foreach ($urls as $url) {
            try {
                $request = Http::timeout((int) config('services.n8n.timeout', 15))
                    ->acceptJson()
                    ->asJson();

                $token = config('services.n8n.token');

                if ($token) {
                    $request = $request->withToken($token);
                }

                $response = $request->post($url, [
                    'message' => $input,
                    'child_name' => $displayName,
                ]);

                if (! $response->successful()) {
                    Log::warning('n8n chat webhook returned non-success response', [
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                return $response->json('reply');
            } catch (\Throwable $e) {
                Log::warning('n8n chat webhook request failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function storeMessage(User|Child $actor, string $role, string $message): ChatbotMessage
    {
        return ChatbotMessage::create([
            'user_id' => $actor instanceof User ? $actor->id : null,
            'child_id' => $actor instanceof Child ? $actor->id : null,
            'role' => $role,
            'message' => $message,
        ]);
    }
}

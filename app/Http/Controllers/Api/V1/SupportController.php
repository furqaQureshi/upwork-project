<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function faqs(): JsonResponse
    {
        $configuredFaqs = AppSetting::get('support_faqs', []);
        $items = is_array($configuredFaqs) ? $configuredFaqs : [];

        $faqs = collect($items)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $question = trim((string) ($item['question'] ?? ''));
                $answer = trim((string) ($item['answer'] ?? ''));

                if ($question === '' || $answer === '') {
                    return null;
                }

                return [
                    'question' => $question,
                    'answer' => $answer,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($faqs === []) {
            $faqs = $this->defaultFaqs();
        }

        return response()->json([
            'data' => $faqs,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->take(20)
            ->get();

        return response()->json([
            'data' => $tickets->map(fn (SupportTicket $ticket): array => $this->serializeTicket($ticket))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $user = $request->user();

        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => trim((string) $validated['subject']),
            'message' => trim((string) $validated['message']),
            'status' => 'open',
            'email_snapshot' => $user->email,
            'phone_snapshot' => $user->phone,
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        return response()->json([
            'data' => $this->serializeTicket($ticket),
            'message' => 'Support ticket submitted successfully.',
        ], 201);
    }

    private function serializeTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'status' => $ticket->status,
            'email_snapshot' => $ticket->email_snapshot,
            'phone_snapshot' => $ticket->phone_snapshot,
            'created_at' => optional($ticket->created_at)?->toIso8601String(),
            'updated_at' => optional($ticket->updated_at)?->toIso8601String(),
        ];
    }

    private function defaultFaqs(): array
    {
        return [
            [
                'question' => 'How do I post a listing?',
                'answer' => 'Tap Sell, fill in your item details, add photos, and submit.',
            ],
            [
                'question' => 'How do I contact a seller?',
                'answer' => 'Open any listing and tap Chat or Call to connect with the seller.',
            ],
            [
                'question' => 'How do I feature my listing?',
                'answer' => 'Go to My Ads and choose Boost on your active listing.',
            ],
        ];
    }
}
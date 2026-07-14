<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Notifications\NewMessageNotification;
use App\Services\AI\TrustSafetyService;
use App\Services\WebPush\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->with(['listing.images', 'buyer', 'seller', 'latestMessage'])
            ->withCount([
                'messages as unread_count' => function ($builder) use ($user): void {
                    $builder
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id);
                },
            ])
            ->where(function ($builder) use ($user): void {
                $builder
                    ->where('buyer_id', $user->id)
                    ->orWhere('seller_id', $user->id);
            })
            ->orderByDesc('last_message_at')
            ->latest()
            ->paginate(20);

        return view('chat.index', [
            'conversations' => $conversations,
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        if (! $conversation->isParticipant($request->user())) {
            abort(403, 'You do not have access to this chat.');
        }

        $conversation->load(['listing.images', 'buyer', 'seller']);

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with(['sender', 'attachments'])
            ->latest()
            ->take(150)
            ->get()
            ->reverse()
            ->values();

        return view('chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'participant' => $conversation->otherParticipant($request->user()),
        ]);
    }

    public function fetchMessages(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $conversation->isParticipant($request->user())) {
            abort(403, 'You do not have access to this chat.');
        }

        $afterId = max((int) $request->integer('after', 0), 0);

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with(['sender', 'attachments'])
            ->when($afterId > 0, fn ($builder) => $builder->where('id', '>', $afterId))
            ->orderBy('id')
            ->get();

        return response()->json([
            'ok' => true,
            'messages' => $messages
                ->map(fn (Message $message): array => $this->serializeMessage($message, (int) $request->user()->id))
                ->values(),
        ]);
    }

    public function storeFromListing(
        Request $request,
        Listing $listing,
        WebPushService $webPushService,
        TrustSafetyService $trustSafetyService
    ): RedirectResponse
    {
        if ($listing->isOwnedBy($request->user())) {
            return back()->with('status', 'You cannot message yourself for your own listing.');
        }

        if ($listing->status !== 'approved') {
            return back()->with('status', 'This listing is not currently available for chat.');
        }

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1500', 'required_without_all:attachment,attachments,voice_note'],
            'attachment' => ['nullable', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar,mp3,wav,ogg,m4a,webm'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar,mp3,wav,ogg,m4a,webm'],
            'voice_note' => ['nullable', 'file', 'max:12288', 'mimetypes:audio/webm,audio/mp4,audio/mpeg,audio/ogg,audio/wav,audio/x-wav,audio/aac'],
        ]);

        $messageBody = trim((string) ($validated['message'] ?? ''));

        if ($messageBody !== '') {
            $assessment = $trustSafetyService->assessChatMessage($messageBody);
            if ((bool) ($assessment['blocked'] ?? false)) {
                $reason = 'This message was blocked by trust & safety checks.';
                $reasons = (array) ($assessment['reasons'] ?? []);
                if ($reasons !== []) {
                    $reason .= ' '.(string) $reasons[0];
                }

                return back()->withInput()->withErrors([
                    'message' => $reason,
                ]);
            }
        }

        $conversation = Conversation::firstOrCreate(
            [
                'listing_id' => $listing->id,
                'buyer_id' => $request->user()->id,
                'seller_id' => $listing->user_id,
            ],
            [
                'last_message_at' => now(),
            ]
        );

        $uploadedAttachments = $this->collectUploadedAttachments($request, (int) $conversation->id);

        if ($messageBody === '' && $uploadedAttachments === []) {
            return back()->withInput()->withErrors([
                'message' => 'Please type a message or attach media before sending.',
            ]);
        }

        $legacyAttachmentColumns = $this->toLegacyAttachmentColumns($uploadedAttachments[0] ?? null);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $messageBody,
            ...$legacyAttachmentColumns,
        ]);

        if ($uploadedAttachments !== []) {
            $message->attachments()->createMany($this->toAttachmentRows($uploadedAttachments));
        }

        $conversation->update(['last_message_at' => now()]);

        $receiver = $listing->user;

        if ($receiver && $receiver->id !== $request->user()->id) {
            $notification = new NewMessageNotification(
                conversationId: $conversation->id,
                messageId: $message->id,
                senderName: $request->user()->name,
                listingTitle: $listing->title,
                messageBody: $this->buildNotificationMessageBody($messageBody, $uploadedAttachments),
            );

            $receiver->notify($notification);
            $webPushService->sendToUser($receiver, $notification->toWebPushPayload());
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function sendMessage(
        Request $request,
        Conversation $conversation,
        WebPushService $webPushService,
        TrustSafetyService $trustSafetyService
    ): RedirectResponse|JsonResponse
    {
        if (! $conversation->isParticipant($request->user())) {
            abort(403, 'You do not have access to this chat.');
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1500', 'required_without_all:attachment,attachments,voice_note'],
            'attachment' => ['nullable', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar,mp3,wav,ogg,m4a,webm'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar,mp3,wav,ogg,m4a,webm'],
            'voice_note' => ['nullable', 'file', 'max:12288', 'mimetypes:audio/webm,audio/mp4,audio/mpeg,audio/ogg,audio/wav,audio/x-wav,audio/aac'],
        ]);

        $messageBody = trim((string) ($validated['body'] ?? ''));

        if ($messageBody !== '') {
            $assessment = $trustSafetyService->assessChatMessage($messageBody);
            if ((bool) ($assessment['blocked'] ?? false)) {
                $reason = 'Message blocked by AI fraud detection.';
                $reasons = (array) ($assessment['reasons'] ?? []);
                if ($reasons !== []) {
                    $reason .= ' '.(string) $reasons[0];
                }

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $reason,
                        'errors' => [
                            'body' => [$reason],
                        ],
                    ], 422);
                }

                return back()->withInput()->withErrors([
                    'body' => $reason,
                ]);
            }
        }

        $uploadedAttachments = $this->collectUploadedAttachments($request, (int) $conversation->id);

        if ($messageBody === '' && $uploadedAttachments === []) {
            $reason = 'Please type a message or attach media before sending.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $reason,
                    'errors' => [
                        'body' => [$reason],
                    ],
                ], 422);
            }

            return back()->withInput()->withErrors([
                'body' => $reason,
            ]);
        }

        $legacyAttachmentColumns = $this->toLegacyAttachmentColumns($uploadedAttachments[0] ?? null);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $messageBody,
            ...$legacyAttachmentColumns,
        ]);

        if ($uploadedAttachments !== []) {
            $message->attachments()->createMany($this->toAttachmentRows($uploadedAttachments));
        }

        $conversation->update([
            'last_message_at' => now(),
        ]);

        $conversation->loadMissing(['buyer', 'seller', 'listing']);

        $receiver = $conversation->buyer_id === $request->user()->id
            ? $conversation->seller
            : $conversation->buyer;

        if ($receiver && $receiver->id !== $request->user()->id) {
            $notification = new NewMessageNotification(
                conversationId: $conversation->id,
                messageId: $message->id,
                senderName: $request->user()->name,
                listingTitle: $conversation->listing->title,
                messageBody: $this->buildNotificationMessageBody($messageBody, $uploadedAttachments),
            );

            $receiver->notify($notification);
            $webPushService->sendToUser($receiver, $notification->toWebPushPayload());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $this->serializeMessage($message->loadMissing(['sender', 'attachments']), (int) $request->user()->id),
            ]);
        }

        return back();
    }

    /**
     * @return array<int, array{path: string, name: string, mime: string, size: int|null, kind: string, sort_order: int}>
     */
    private function collectUploadedAttachments(Request $request, int $conversationId): array
    {
        /** @var array<int, UploadedFile> $files */
        $files = [];

        $singleAttachment = $request->file('attachment');
        if ($singleAttachment instanceof UploadedFile) {
            $files[] = $singleAttachment;
        }

        foreach ((array) $request->file('attachments', []) as $file) {
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        $payloads = [];

        foreach ($files as $file) {
            $payload = $this->storeUploadedAttachment($file, $conversationId);
            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }

        $voiceNote = $request->file('voice_note');
        if ($voiceNote instanceof UploadedFile) {
            $voicePayload = $this->storeUploadedAttachment($voiceNote, $conversationId, 'audio');
            if ($voicePayload !== null) {
                $payloads[] = $voicePayload;
            }
        }

        $orderedPayloads = [];

        foreach ($payloads as $index => $payload) {
            $orderedPayloads[] = [
                ...$payload,
                'sort_order' => $index,
            ];
        }

        return $orderedPayloads;
    }

    /**
     * @return array{path: string, name: string, mime: string, size: int|null, kind: string}|null
     */
    private function storeUploadedAttachment(?UploadedFile $file, int $conversationId, ?string $forceKind = null): ?array
    {
        if (! $file) {
            return null;
        }

        $mime = (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream');

        return [
            'path' => $file->store('chat/'.$conversationId, 'public'),
            'name' => (string) $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => $file->getSize(),
            'kind' => $forceKind ?? $this->resolveAttachmentKind($mime),
        ];
    }

    /**
     * @param  array<int, array{path: string, name: string, mime: string, size: int|null, kind: string, sort_order: int}>  $attachments
     * @return array<int, array{path: string, name: string, mime: string, size: int|null, kind: string, sort_order: int}>
     */
    private function toAttachmentRows(array $attachments): array
    {
        return array_map(
            static fn (array $attachment): array => [
                'path' => $attachment['path'],
                'name' => $attachment['name'],
                'mime' => $attachment['mime'],
                'size' => $attachment['size'],
                'kind' => $attachment['kind'],
                'sort_order' => $attachment['sort_order'],
            ],
            $attachments
        );
    }

    /**
     * @param  array{path: string, name: string, mime: string, size: int|null, kind: string, sort_order: int}|null  $attachment
     * @return array{attachment_path: string|null, attachment_name: string|null, attachment_mime: string|null, attachment_size: int|null, attachment_kind: string|null}
     */
    private function toLegacyAttachmentColumns(?array $attachment): array
    {
        if ($attachment === null) {
            return [
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
                'attachment_size' => null,
                'attachment_kind' => null,
            ];
        }

        return [
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachment_mime' => $attachment['mime'],
            'attachment_size' => $attachment['size'],
            'attachment_kind' => $attachment['kind'],
        ];
    }

    /**
     * @param  array<int, array{path: string, name: string, mime: string, size: int|null, kind: string, sort_order: int}>  $attachments
     */
    private function buildNotificationMessageBody(string $body, array $attachments): string
    {
        $normalizedBody = trim($body);

        if ($normalizedBody !== '') {
            return $normalizedBody;
        }

        $attachmentCount = count($attachments);

        if ($attachmentCount === 0) {
            return 'Sent a message';
        }

        if ($attachmentCount === 1) {
            $kind = (string) ($attachments[0]['kind'] ?? 'file');

            return match ($kind) {
                'image' => 'Sent a photo',
                'audio' => 'Sent a voice note',
                default => 'Sent an attachment',
            };
        }

        return 'Sent '.$attachmentCount.' attachments';
    }

    private function resolveAttachmentKind(string $mime): string
    {
        $normalizedMime = strtolower($mime);

        if (str_starts_with($normalizedMime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($normalizedMime, 'audio/')) {
            return 'audio';
        }

        return 'file';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(Message $message, int $viewerId): array
    {
        $createdAt = $message->created_at ?? now();
        $attachments = $message->resolved_attachments;
        $primaryAttachment = $attachments[0] ?? null;

        return [
            'id' => (int) $message->id,
            'body' => (string) $message->body,
            'is_own' => (int) $message->sender_id === $viewerId,
            'time' => $createdAt->format('g:i A'),
            'day_key' => $createdAt->toDateString(),
            'day_label' => $createdAt->isToday() ? 'Today' : ($createdAt->isYesterday() ? 'Yesterday' : $createdAt->format('F j')),
            'read_at' => $message->read_at?->toIso8601String(),
            'attachments' => $attachments,
            'attachment_url' => $primaryAttachment['url'] ?? null,
            'attachment_name' => $primaryAttachment['name'] ?? null,
            'attachment_kind' => $primaryAttachment['kind'] ?? null,
            'attachment_mime' => $primaryAttachment['mime'] ?? null,
            'attachment_size' => $primaryAttachment['size'] ?? null,
        ];
    }
}

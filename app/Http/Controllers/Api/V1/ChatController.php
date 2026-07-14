<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->input('per_page', 20), 50));

        $conversations = Conversation::query()
            ->with(['listing.images', 'buyer', 'seller', 'latestMessage.attachments'])
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
            ->paginate($perPage);

        return response()->json([
            'data' => $conversations
                ->getCollection()
                ->map(fn (Conversation $conversation): array => $this->serializeConversation($conversation, (int) $user->id))
                ->values(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
                'per_page' => $conversations->perPage(),
                'has_more' => $conversations->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
        ]);

        /** @var Listing $listing */
        $listing = Listing::findOrFail($validated['listing_id']);
        abort_if($listing->user_id === $user->id, 422, 'You cannot start a chat with yourself.');

        $conversation = Conversation::firstOrCreate(
            ['listing_id' => $listing->id, 'buyer_id' => $user->id],
            ['seller_id' => $listing->user_id]
        );

        $conversation->load(['listing.images', 'buyer', 'seller', 'latestMessage.attachments']);
        $conversation->loadCount([
            'messages as unread_count' => function ($builder) use ($user): void {
                $builder->whereNull('read_at')->where('sender_id', '!=', $user->id);
            },
        ]);

        return response()->json(['data' => $this->serializeConversation($conversation, (int) $user->id)], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->input('per_page', 30), 100));

        abort_unless($conversation->isParticipant($user), 403, 'You do not have access to this chat.');

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with(['sender', 'attachments'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $messages
                ->getCollection()
                ->reverse()
                ->values()
                ->map(fn (Message $message): array => $this->serializeMessage($message, (int) $user->id))
                ->values(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'has_more' => $messages->hasMorePages(),
            ],
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), 403, 'You do not have access to this chat.');

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1500', 'required_without_all:attachment,attachments,voice_note'],
            'attachment' => ['nullable', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar,mp3,wav,ogg,m4a,webm,mp4,mov'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar,mp3,wav,ogg,m4a,webm,mp4,mov'],
            'voice_note' => ['nullable', 'file', 'max:12288', 'mimetypes:audio/webm,audio/mp4,audio/mpeg,audio/ogg,audio/wav,audio/x-wav,audio/aac'],
        ]);

        $messageBody = trim((string) ($validated['body'] ?? ''));
        $uploadedAttachments = $this->collectUploadedAttachments($request, (int) $conversation->id);

        if ($messageBody === '' && $uploadedAttachments === []) {
            return response()->json([
                'message' => 'Please type a message or attach media before sending.',
                'errors' => [
                    'body' => ['Please type a message or attach media before sending.'],
                ],
            ], 422);
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $messageBody,
            ...$this->toLegacyAttachmentColumns($uploadedAttachments[0] ?? null),
        ]);

        if ($uploadedAttachments !== []) {
            $message->attachments()->createMany($this->toAttachmentRows($uploadedAttachments));
        }

        $conversation->update(['last_message_at' => now()]);
        $message->load(['sender', 'attachments']);

        return response()->json([
            'data' => $this->serializeMessage($message, (int) $user->id),
        ], 201);
    }

    private function serializeConversation(Conversation $conversation, int $currentUserId): array
    {
        $otherUser = $conversation->buyer_id === $currentUserId ? $conversation->seller : $conversation->buyer;
        $role = $conversation->buyer_id === $currentUserId ? 'buyer' : 'seller';

        return [
            'id' => $conversation->id,
            'listing' => $conversation->listing ? [
                'id' => $conversation->listing->id,
                'title' => $conversation->listing->title,
                'slug' => $conversation->listing->slug,
                'main_image_url' => $conversation->listing->main_image_url,
            ] : null,
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
            ] : null,
            'latest_message' => $conversation->latestMessage
                ? $this->serializeMessage($conversation->latestMessage, $currentUserId)
                : null,
            'role' => $role,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
        ];
    }

    private function serializeMessage(Message $message, int $currentUserId): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'body' => $message->body,
            'is_mine' => $message->sender_id === $currentUserId,
            'attachments' => $message->resolved_attachments,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'read_at' => optional($message->read_at)?->toIso8601String(),
        ];
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

    private function resolveAttachmentKind(string $mime): string
    {
        $normalized = strtolower(trim($mime));

        if (str_starts_with($normalized, 'image/')) {
            return 'image';
        }

        if (str_starts_with($normalized, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($normalized, 'video/')) {
            return 'video';
        }

        return 'file';
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
}

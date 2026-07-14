<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'attachment_kind',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'attachment_size' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        $firstAttachment = $this->resolved_attachments[0] ?? null;

        return $firstAttachment['url'] ?? null;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<int, array{name: string, url: string, mime: string|null, size: int|null, kind: string}>
     */
    public function getResolvedAttachmentsAttribute(): array
    {
        $attachments = $this->relationLoaded('attachments')
            ? $this->attachments
            : $this->attachments()->get();

        $resolved = $attachments
            ->map(function (MessageAttachment $attachment): ?array {
                $url = $attachment->url;

                if (! $url) {
                    return null;
                }

                return [
                    'name' => (string) $attachment->name,
                    'url' => $url,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                    'kind' => (string) ($attachment->kind ?: 'file'),
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($resolved !== []) {
            return $resolved;
        }

        if (! $this->attachment_path) {
            return [];
        }

        $legacyPath = (string) $this->attachment_path;
        $legacyUrl = preg_match('/^https?:\/\//i', $legacyPath) === 1
            ? $legacyPath
            : '/storage/'.ltrim($legacyPath, '/');

        return [[
            'name' => (string) ($this->attachment_name ?: 'Attachment'),
            'url' => $legacyUrl,
            'mime' => $this->attachment_mime,
            'size' => $this->attachment_size,
            'kind' => (string) ($this->attachment_kind ?: 'file'),
        ]];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}

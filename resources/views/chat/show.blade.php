@php
    $participantName = trim((string) ($participant?->name ?? 'User'));
    $participantInitials = collect(preg_split('/\s+/', $participantName) ?: [])
        ->filter(fn ($part) => trim((string) $part) !== '')
        ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
        ->take(2)
        ->implode('');
    $participantInitials = $participantInitials !== '' ? $participantInitials : 'U';
    $participantAvatarUrl = null;
    if ($participant?->avatar) {
        $avatarPath = (string) $participant->avatar;
        $participantAvatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
            ? $avatarPath
            : '/storage/'.ltrim($avatarPath, '/');
    }
    $participantStatusLabel = 'Last seen not available';
    $participantStatusClasses = 'bg-slate-100 text-slate-600';

    if ($participant?->last_seen_at) {
        if ($participant->last_seen_at->gt(now()->subMinutes(5))) {
            $participantStatusLabel = 'Online';
            $participantStatusClasses = 'bg-emerald-100 text-emerald-700';
        } else {
            $participantStatusLabel = 'Last seen '.$participant->last_seen_at->diffForHumans();
        }
    }

    $currencySymbol = (string) setting('site_currency_symbol', 'Rs');
    $listingPriceLabel = ($conversation->listing->price_type ?? 'fixed') === 'free'
        ? 'FREE'
        : $currencySymbol.number_format((float) $conversation->listing->price);

    $messageGroups = $messages->groupBy(fn ($message) => $message->created_at->toDateString());
    $quickReplies = [
        'Is this still available?',
        "What's your best price?",
        'Can you share more details?',
        'Can you deliver this?',
    ];
@endphp

<x-app-layout>
    <div class="min-h-[calc(100dvh-4rem)] bg-[radial-gradient(circle_at_top,_rgba(251,146,60,0.10),_transparent_30%),linear-gradient(180deg,_#f8fafc_0%,_#eef2ff_100%)]"
         x-data="chatThread({
             initialComposer: @js((string) old('body', '')),
             pollUrl: @js(route('chat.messages', $conversation)),
             sendUrl: @js(route('chat.message', $conversation)),
             csrfToken: @js(csrf_token()),
             lastMessageId: @js((int) ($messages->last()->id ?? 0)),
         })"
         x-init="init()">

        <section class="sticky top-16 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
            <div class="px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <a href="{{ route('chat.index') }}"
                       class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm hover:border-orange-300 hover:text-orange-600"
                       aria-label="Back to chats">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                        </svg>
                    </a>

                    <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-orange-100 text-sm font-black uppercase text-orange-700 ring-2 ring-white">
                        @if ($participantAvatarUrl)
                            <img src="{{ $participantAvatarUrl }}" alt="{{ $participantName }}" class="h-full w-full object-cover">
                        @else
                            <span>{{ $participantInitials }}</span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h1 class="truncate font-display text-lg font-bold text-slate-900 sm:text-xl">{{ $participantName }}</h1>
                            <span class="hidden rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide sm:inline-flex {{ $participantStatusClasses }}">
                                {{ $participantStatusLabel }}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span class="inline-flex rounded-full px-2 py-0.5 font-bold uppercase tracking-wide {{ $participantStatusClasses }} sm:hidden">
                                {{ $participantStatusLabel }}
                            </span>
                            <span class="truncate">{{ $conversation->listing->title }}</span>
                        </div>
                    </div>

                    <details class="relative shrink-0">
                        <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-orange-300 hover:text-orange-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <circle cx="12" cy="5" r="1.75" />
                                <circle cx="12" cy="12" r="1.75" />
                                <circle cx="12" cy="19" r="1.75" />
                            </svg>
                        </summary>
                        <div class="absolute right-0 mt-2 w-44 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                            <a href="{{ route('listings.show', $conversation->listing) }}" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-orange-50 hover:text-orange-700">View ad</a>
                            <a href="{{ route('chat.index') }}" class="mt-1 block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">All chats</a>
                        </div>
                    </details>
                </div>

                <a href="{{ route('listings.show', $conversation->listing) }}" class="mt-3 flex items-center gap-3 rounded-[1.4rem] border border-slate-200 bg-slate-50 px-3 py-2.5 shadow-sm transition hover:border-orange-300 hover:bg-orange-50/70">
                    <img src="{{ $conversation->listing->main_image_url }}" alt="{{ $conversation->listing->title }}" class="h-14 w-14 shrink-0 rounded-2xl object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-black uppercase tracking-[0.14em] text-orange-600">Related Ad</p>
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $conversation->listing->title }}</p>
                        <p class="truncate text-xs font-semibold text-slate-500">{{ $listingPriceLabel }} • {{ $conversation->listing->city }}</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
                    </svg>
                </a>
            </div>
        </section>

        <section class="px-3 pb-[calc(env(safe-area-inset-bottom)+11.5rem)] pt-4 sm:px-6 sm:pb-44 lg:px-8">
            <div x-ref="messageViewport" data-message-viewport class="max-h-[calc(100dvh-18.5rem)] space-y-4 overflow-y-auto pr-1 sm:max-h-[calc(100dvh-20rem)]">
                @if ($messageGroups->isEmpty())
                    <div data-empty-state class="flex min-h-[34vh] items-center justify-center">
                        <div class="max-w-sm rounded-[2rem] border border-dashed border-slate-300 bg-white/85 px-6 py-8 text-center shadow-sm">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-4l-3 3z" />
                                </svg>
                            </div>
                            <p class="mt-4 font-display text-xl font-bold text-slate-900">Start the conversation</p>
                            <p class="mt-2 text-sm text-slate-600">Ask about availability, price, or delivery. Quick replies are ready below.</p>
                        </div>
                    </div>
                @else
                    @foreach ($messageGroups as $dateKey => $groupedMessages)
                        @php
                            $date = \Illuminate\Support\Carbon::parse($dateKey);
                            $separatorLabel = $date->isToday()
                                ? 'Today'
                                : ($date->isYesterday() ? 'Yesterday' : $date->format('F j'));
                        @endphp

                        <div class="flex justify-center" data-day-separator="{{ $date->toDateString() }}">
                            <span class="rounded-full bg-white/90 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 shadow-sm ring-1 ring-slate-200/80">
                                {{ $separatorLabel }}
                            </span>
                        </div>

                        @foreach ($groupedMessages as $message)
                            @php
                                $isOwnMessage = $message->sender_id === auth()->id();
                                $messageTime = $message->created_at->format('g:i A');
                            @endphp

                            <div class="flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}"
                                 data-chat-message
                                 data-message-id="{{ $message->id }}"
                                 data-day-key="{{ $message->created_at->toDateString() }}">
                                <div class="max-w-[86%] sm:max-w-[72%]">
                                    @php
                                        $messageAttachments = collect($message->resolved_attachments ?? []);
                                    @endphp

                                    <div class="rounded-[1.75rem] px-4 py-3 text-sm leading-relaxed shadow-sm ring-1 {{ $isOwnMessage ? 'bg-orange-500 text-white ring-orange-400/40' : 'bg-white text-slate-800 ring-slate-200' }}">
                                        @if ($messageAttachments->isNotEmpty())
                                            <div class="space-y-2">
                                                @foreach ($messageAttachments as $attachment)
                                                    @if (($attachment['kind'] ?? 'file') === 'image')
                                                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl ring-1 {{ $isOwnMessage ? 'ring-orange-300/70' : 'ring-slate-200' }}">
                                                            <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] ?? 'Image attachment' }}" class="max-h-72 w-full object-cover">
                                                        </a>
                                                    @elseif (($attachment['kind'] ?? 'file') === 'audio')
                                                        <audio controls preload="none" class="w-full rounded-xl bg-black/5 p-2">
                                                            <source src="{{ $attachment['url'] }}" type="{{ $attachment['mime'] ?? 'audio/webm' }}">
                                                        </audio>
                                                    @else
                                                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold {{ $isOwnMessage ? 'bg-orange-400/30 text-white' : 'bg-slate-100 text-slate-700' }}">
                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 10v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-8" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0-3-3m3 3 3-3" />
                                                            </svg>
                                                            <span>{{ $attachment['name'] ?? 'Download attachment' }}</span>
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        @if (trim((string) $message->body) !== '')
                                            <p class="{{ $messageAttachments->isNotEmpty() ? 'mt-2' : '' }}">{!! nl2br(e($message->body)) !!}</p>
                                        @endif
                                    </div>

                                    <div class="mt-1.5 flex items-center gap-1.5 px-1 text-[11px] font-semibold {{ $isOwnMessage ? 'justify-end text-orange-700' : 'justify-start text-slate-500' }}">
                                        <span>{{ $messageTime }}</span>

                                        @if ($isOwnMessage)
                                            @if ($message->read_at)
                                                <span class="inline-flex items-center text-sky-500" aria-label="Read">
                                                    <svg class="h-3.5 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 10.5 6.5 14.5 10.5 9.5" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5 12 14.5 17.5 7.5" />
                                                    </svg>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-orange-500" aria-label="Sent">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 7 14.5 16.5 5.5" />
                                                    </svg>
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                @endif
            </div>
        </section>

        <section class="fixed inset-x-0 bottom-0 z-[70] border-t border-slate-200 bg-white/95 backdrop-blur-xl">
            <div class="px-3 pb-[calc(env(safe-area-inset-bottom)+0.85rem)] pt-3 sm:px-6 lg:px-8">
                <div class="flex gap-2 overflow-x-auto pb-2">
                    @foreach ($quickReplies as $quickReply)
                        <button type="button"
                                @click="applyQuickReply(@js($quickReply))"
                                class="shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700">
                            {{ $quickReply }}
                        </button>
                    @endforeach
                </div>

                <p x-show="composerHint" x-cloak x-text="composerHint" class="mb-2 text-xs font-semibold text-slate-500"></p>

                <div x-cloak
                     x-show="selectedUploads.length > 0 || isRecording || isSending"
                     class="mb-2 space-y-2">
                    <template x-for="(upload, index) in selectedUploads" :key="upload.id">
                        <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-orange-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path x-show="upload.kind !== 'image'" stroke-linecap="round" stroke-linejoin="round" d="M7 10v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-8" />
                                        <path x-show="upload.kind !== 'image'" stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0-3-3m3 3 3-3" />
                                        <rect x="4" y="5" width="16" height="12" rx="2" x-show="upload.kind === 'image'" />
                                        <path x-show="upload.kind === 'image'" stroke-linecap="round" stroke-linejoin="round" d="m8 12 2.5-2.5L14 13l2-2 2 2" />
                                    </svg>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold text-slate-800" x-text="upload.name"></p>
                                    <p class="mt-0.5 text-[11px] text-slate-500" x-text="formatBytes(upload.size)"></p>

                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full bg-orange-500 transition-all" :style="`width: ${upload.progress}%`"></div>
                                    </div>
                                </div>

                                <button type="button"
                                        x-show="!isSending"
                                        @click="removeSelectedUpload(index)"
                                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        aria-label="Remove upload">
                                    x
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="isRecording" x-cloak class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        <span>Recording...</span>
                        <button type="button" @click="stopVoiceRecording()" class="rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-bold text-white">Stop</button>
                    </div>

                    <div x-show="isSending" x-cloak class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-sky-500"></span>
                        <span x-text="`Uploading ${uploadProgress}%`"></span>
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('chat.message', $conversation) }}"
                      enctype="multipart/form-data"
                      @submit.prevent="submitComposer()"
                      class="space-y-2">
                    @csrf
                    <x-input-error :messages="$errors->get('body')" class="mt-0" />
                    <x-input-error :messages="$errors->get('attachment')" class="mt-0" />
                          <x-input-error :messages="$errors->get('attachments')" class="mt-0" />
                          <x-input-error :messages="$errors->get('attachments.*')" class="mt-0" />
                    <x-input-error :messages="$errors->get('voice_note')" class="mt-0" />

                    <input type="file"
                              name="attachments[]"
                           x-ref="attachmentInput"
                           class="hidden"
                              multiple
                           @change="handleAttachmentSelected($event)">

                    <input type="file"
                              name="attachments[]"
                           x-ref="galleryInput"
                           class="hidden"
                           accept="image/*"
                              multiple
                           @change="handleGallerySelected($event)">

                    <input type="file"
                           name="voice_note"
                           x-ref="voiceInput"
                           class="hidden"
                           accept="audio/*"
                           @change="handleVoiceFileSelected($event)">

                    <div class="flex items-end gap-2 rounded-[1.8rem] border border-slate-200 bg-white px-3 py-2 shadow-[0_18px_45px_-28px_rgba(15,23,42,0.45)]">
                        <button type="button"
                                @click="openAttachmentPicker()"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-orange-600"
                                aria-label="Attachment options">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.2-9.19a4 4 0 1 1 5.65 5.65l-9.2 9.2a2 2 0 1 1-2.82-2.83l8.48-8.48" />
                            </svg>
                        </button>

                        <button type="button"
                                @click="openGalleryPicker()"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-orange-600"
                                aria-label="Gallery options">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8 13 2.5-2.5L14 14l2-2 2 2" />
                                <circle cx="8.5" cy="9" r="1" />
                            </svg>
                        </button>

                        <textarea id="body"
                                  name="body"
                                  x-ref="body"
                                  x-model="composer"
                                  @input="autoGrow()"
                                  @keydown.enter.exact.prevent="submitComposer()"
                                  rows="1"
                                  placeholder="Type a message..."
                                  class="min-h-[2.75rem] max-h-36 flex-1 resize-none border-0 bg-transparent px-0 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-0"></textarea>

                        <button type="button"
                                @click="toggleVoiceRecording()"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-orange-600"
                                :class="isRecording ? 'bg-rose-100 text-rose-600' : ''"
                                aria-label="Voice message options">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19v2" />
                            </svg>
                        </button>

                        <button type="submit"
                                :disabled="isSending || isRecording || (composer.trim() === '' && selectedUploads.length === 0)"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white shadow-lg shadow-orange-200 transition hover:bg-orange-600 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                                aria-label="Send message">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3.4 20.6 21 12 3.4 3.4 3.3 10l12.1 2-12.1 2z" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        function chatThread(config) {
            return {
                composer: (config && config.initialComposer) ? config.initialComposer : '',
                pollUrl: (config && config.pollUrl) ? config.pollUrl : '',
                sendUrl: (config && config.sendUrl) ? config.sendUrl : '',
                csrfToken: (config && config.csrfToken) ? config.csrfToken : '',
                lastMessageId: Number((config && config.lastMessageId) ? config.lastMessageId : 0),
                composerHint: '',
                hintTimer: null,
                pollTimer: null,
                selectedUploads: [],
                uploadProgress: 0,
                mediaRecorder: null,
                recordingChunks: [],
                isRecording: false,
                isSending: false,

                init() {
                    this.$nextTick(() => {
                        this.autoGrow();
                        this.scrollToBottom();
                        this.hydrateLastMessageId();
                        this.startPolling();
                    });

                    window.addEventListener('beforeunload', () => {
                        this.stopPolling();
                    });
                },

                autoGrow() {
                    const textarea = this.$refs.body;
                    if (!textarea) {
                        return;
                    }

                    textarea.style.height = '0px';
                    textarea.style.height = Math.min(textarea.scrollHeight, 144) + 'px';
                },

                scrollToBottom() {
                    const viewport = this.$refs.messageViewport;
                    if (!viewport) {
                        return;
                    }

                    viewport.scrollTop = viewport.scrollHeight;
                },

                applyQuickReply(text) {
                    this.composer = String(text || '');
                    this.composerHint = '';

                    this.$nextTick(() => {
                        this.autoGrow();

                        if (this.$refs.body) {
                            this.$refs.body.focus();
                        }
                    });
                },

                showComposerHint(text) {
                    this.composerHint = String(text || '').trim();

                    if (this.hintTimer) {
                        window.clearTimeout(this.hintTimer);
                    }

                    this.hintTimer = window.setTimeout(() => {
                        this.composerHint = '';
                    }, 2600);
                },

                hydrateLastMessageId() {
                    const viewport = this.$refs.messageViewport;
                    if (!viewport) {
                        return;
                    }

                    const ids = Array.from(viewport.querySelectorAll('[data-message-id]'))
                        .map((node) => Number(node.getAttribute('data-message-id') || 0))
                        .filter((id) => id > 0);

                    if (ids.length > 0) {
                        this.lastMessageId = Math.max(this.lastMessageId, ...ids);
                    }
                },

                startPolling() {
                    if (!this.pollUrl) {
                        return;
                    }

                    this.stopPolling();

                    this.pollTimer = window.setInterval(() => {
                        this.pollForMessages();
                    }, 4000);
                },

                stopPolling() {
                    if (this.pollTimer) {
                        window.clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                },

                isNearBottom() {
                    const viewport = this.$refs.messageViewport;
                    if (!viewport) {
                        return true;
                    }

                    return viewport.scrollTop + viewport.clientHeight >= viewport.scrollHeight - 140;
                },

                async pollForMessages() {
                    if (!this.pollUrl || this.isSending || this.isRecording) {
                        return;
                    }

                    try {
                        const response = await fetch(`${this.pollUrl}?after=${encodeURIComponent(String(this.lastMessageId || 0))}`, {
                            method: 'GET',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        const incoming = Array.isArray(payload.messages) ? payload.messages : [];
                        if (incoming.length === 0) {
                            return;
                        }

                        const shouldStick = this.isNearBottom();
                        incoming.forEach((message) => this.appendMessage(message, shouldStick));
                    } catch (_error) {
                        // Keep polling resilient; temporary network errors should self-recover.
                    }
                },

                openAttachmentPicker() {
                    const input = this.$refs.attachmentInput;
                    if (!input) {
                        return;
                    }

                    input.setAttribute('accept', 'image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,.mp3,.wav,.ogg,.m4a,.webm');
                    input.click();
                },

                openGalleryPicker() {
                    const input = this.$refs.galleryInput;
                    if (!input) {
                        return;
                    }

                    input.click();
                },

                handleAttachmentSelected(event) {
                    const files = event && event.target ? event.target.files : null;
                    this.addSelectedFiles(files);

                    if (event && event.target) {
                        event.target.value = '';
                    }
                },

                handleGallerySelected(event) {
                    const files = event && event.target ? event.target.files : null;
                    this.addSelectedFiles(files, 'image');

                    if (event && event.target) {
                        event.target.value = '';
                    }
                },

                addSelectedFiles(fileList, forcedKind = null) {
                    const files = Array.from(fileList || []);
                    if (files.length === 0) {
                        return;
                    }

                    files.forEach((file) => {
                        this.selectedUploads.push(this.createSelectedUpload(file, forcedKind));
                    });

                    const countLabel = files.length === 1 ? '1 file added.' : `${files.length} files added.`;
                    this.showComposerHint(countLabel);
                },

                createSelectedUpload(file, forcedKind = null) {
                    return {
                        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                        file,
                        name: String(file && file.name ? file.name : 'Attachment'),
                        size: Number(file && file.size ? file.size : 0),
                        kind: forcedKind || this.resolveUploadKind(file),
                        progress: 0,
                    };
                },

                resolveUploadKind(file) {
                    const mime = String(file && file.type ? file.type : '').toLowerCase();

                    if (mime.startsWith('image/')) {
                        return 'image';
                    }

                    if (mime.startsWith('audio/')) {
                        return 'audio';
                    }

                    return 'file';
                },

                removeSelectedUpload(index) {
                    if (this.isSending) {
                        return;
                    }

                    if (index < 0 || index >= this.selectedUploads.length) {
                        return;
                    }

                    this.selectedUploads.splice(index, 1);
                },

                clearSelectedUploads() {
                    this.selectedUploads = [];
                    this.uploadProgress = 0;

                    if (this.$refs.attachmentInput) {
                        this.$refs.attachmentInput.value = '';
                    }

                    if (this.$refs.galleryInput) {
                        this.$refs.galleryInput.value = '';
                    }

                    if (this.$refs.voiceInput) {
                        this.$refs.voiceInput.value = '';
                    }
                },

                formatBytes(size) {
                    const bytes = Number(size || 0);
                    if (bytes <= 0) {
                        return '0 KB';
                    }

                    const kb = bytes / 1024;
                    if (kb < 1024) {
                        return `${Math.max(1, Math.round(kb))} KB`;
                    }

                    return `${(kb / 1024).toFixed(1)} MB`;
                },

                async toggleVoiceRecording() {
                    if (this.isRecording) {
                        this.stopVoiceRecording();
                        return;
                    }

                    if (!navigator.mediaDevices || typeof window.MediaRecorder === 'undefined') {
                        if (this.$refs.voiceInput) {
                            this.$refs.voiceInput.click();
                        }

                        return;
                    }

                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.recordingChunks = [];

                        this.mediaRecorder = new window.MediaRecorder(stream);
                        this.mediaRecorder.ondataavailable = (event) => {
                            if (event.data && event.data.size > 0) {
                                this.recordingChunks.push(event.data);
                            }
                        };

                        this.mediaRecorder.onstop = () => {
                            const mimeType = (this.recordingChunks[0] && this.recordingChunks[0].type)
                                ? this.recordingChunks[0].type
                                : 'audio/webm';
                            const blob = new Blob(this.recordingChunks, { type: mimeType });

                            stream.getTracks().forEach((track) => track.stop());

                            if (blob.size > 0) {
                                const extension = this.audioExtensionForMime(mimeType);
                                const file = new File([
                                    blob,
                                ], `voice-note-${Date.now()}.${extension}`, { type: mimeType });

                                this.addSelectedFiles([file], 'audio');
                            }

                            this.isRecording = false;
                            this.mediaRecorder = null;
                            this.recordingChunks = [];
                        };

                        this.mediaRecorder.start();
                        this.isRecording = true;
                        this.showComposerHint('Recording voice note...');
                    } catch (_error) {
                        if (this.$refs.voiceInput) {
                            this.$refs.voiceInput.click();
                        }
                    }
                },

                stopVoiceRecording() {
                    if (this.mediaRecorder && this.isRecording) {
                        this.mediaRecorder.stop();
                    }
                },

                handleVoiceFileSelected(event) {
                    const file = event && event.target && event.target.files ? event.target.files[0] : null;
                    if (!file) {
                        return;
                    }

                    this.addSelectedFiles([file], 'audio');

                    if (event && event.target) {
                        event.target.value = '';
                    }
                },

                audioExtensionForMime(mimeType) {
                    const normalized = String(mimeType || '').toLowerCase();

                    if (normalized.includes('mpeg')) {
                        return 'mp3';
                    }

                    if (normalized.includes('wav')) {
                        return 'wav';
                    }

                    if (normalized.includes('ogg')) {
                        return 'ogg';
                    }

                    if (normalized.includes('mp4')) {
                        return 'm4a';
                    }

                    return 'webm';
                },

                async submitComposer() {
                    const body = this.composer.trim();

                    if (this.isSending || this.isRecording) {
                        return;
                    }

                    if (body === '' && this.selectedUploads.length === 0) {
                        return;
                    }

                    this.isSending = true;
                    this.uploadProgress = this.selectedUploads.length > 0 ? 2 : 100;
                    this.updateUploadProgress(this.uploadProgress);

                    const formData = new FormData();
                    formData.append('_token', this.csrfToken);

                    if (body !== '') {
                        formData.append('body', body);
                    }

                    this.selectedUploads.forEach((upload) => {
                        formData.append('attachments[]', upload.file, upload.name);
                    });

                    try {
                        const payload = await this.sendWithProgress(formData);

                        if (payload && payload.message) {
                            this.appendMessage(payload.message, true);
                        }

                        this.composer = '';
                        this.autoGrow();
                        this.clearSelectedUploads();
                        this.composerHint = '';
                    } catch (error) {
                        if (error && error.validation && error.payload) {
                            const firstError = this.extractFirstError(error.payload.errors || {});
                            if (firstError) {
                                this.showComposerHint(firstError);
                            }
                        } else {
                            this.showComposerHint('Unable to send right now. Please try again.');
                        }
                    } finally {
                        this.isSending = false;
                        this.uploadProgress = 0;

                        this.$nextTick(() => {
                            if (this.$refs.body) {
                                this.$refs.body.focus();
                            }
                        });
                    }
                },

                sendWithProgress(formData) {
                    return new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', this.sendUrl, true);
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                        xhr.upload.onprogress = (event) => {
                            if (!event.lengthComputable || event.total <= 0) {
                                return;
                            }

                            const percent = Math.max(1, Math.min(100, Math.round((event.loaded / event.total) * 100)));
                            this.uploadProgress = percent;
                            this.updateUploadProgress(percent);
                        };

                        xhr.onreadystatechange = () => {
                            if (xhr.readyState !== 4) {
                                return;
                            }

                            let payload = {};
                            try {
                                payload = JSON.parse(xhr.responseText || '{}');
                            } catch (_error) {
                                payload = {};
                            }

                            if (xhr.status >= 200 && xhr.status < 300) {
                                this.uploadProgress = 100;
                                this.updateUploadProgress(100);
                                resolve(payload);
                                return;
                            }

                            if (xhr.status === 422) {
                                reject({ validation: true, payload });
                                return;
                            }

                            reject({ validation: false, payload });
                        };

                        xhr.onerror = () => {
                            reject({ validation: false, payload: {} });
                        };

                        xhr.send(formData);
                    });
                },

                updateUploadProgress(percent) {
                    const value = Math.max(1, Math.min(100, Number(percent || 0)));
                    this.selectedUploads.forEach((upload) => {
                        upload.progress = value;
                    });
                },

                extractFirstError(errors) {
                    const allMessages = Object.values(errors || {}).flat();
                    const first = allMessages[0];

                    return first ? String(first) : '';
                },

                appendMessage(message, shouldScroll) {
                    if (!message || !message.id) {
                        return;
                    }

                    const viewport = this.$refs.messageViewport;
                    if (!viewport) {
                        return;
                    }

                    if (viewport.querySelector(`[data-message-id="${message.id}"]`)) {
                        this.lastMessageId = Math.max(this.lastMessageId, Number(message.id));
                        return;
                    }

                    const emptyState = viewport.querySelector('[data-empty-state]');
                    if (emptyState) {
                        emptyState.remove();
                    }

                    const dayKey = String(message.day_key || '');
                    if (dayKey !== '') {
                        const hasSeparator = Array.from(viewport.querySelectorAll('[data-day-separator]'))
                            .some((node) => node.getAttribute('data-day-separator') === dayKey);

                        if (!hasSeparator) {
                            const separator = document.createElement('div');
                            separator.className = 'flex justify-center';
                            separator.setAttribute('data-day-separator', dayKey);
                            separator.innerHTML = `<span class="rounded-full bg-white/90 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500 shadow-sm ring-1 ring-slate-200/80">${this.escapeHtml(String(message.day_label || dayKey))}</span>`;
                            viewport.appendChild(separator);
                        }
                    }

                    const isOwn = Boolean(message.is_own);
                    const wrapper = document.createElement('div');
                    wrapper.className = `flex ${isOwn ? 'justify-end' : 'justify-start'}`;
                    wrapper.setAttribute('data-chat-message', '1');
                    wrapper.setAttribute('data-message-id', String(message.id));
                    wrapper.setAttribute('data-day-key', dayKey);

                    const bodyHtml = this.formatBodyText(message.body || '');
                    const attachments = this.normalizeAttachments(message);
                    const attachmentsHtml = this.buildAttachmentsHtml(attachments, isOwn);
                    const hasBody = String(message.body || '').trim() !== '';
                    const bodyWithSpacing = hasBody
                        ? `<p class="${attachments.length > 0 ? 'mt-2' : ''}">${bodyHtml}</p>`
                        : '';

                    const tickHtml = isOwn
                        ? (message.read_at
                            ? '<span class="inline-flex items-center text-sky-500" aria-label="Read"><svg class="h-3.5 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 10.5 6.5 14.5 10.5 9.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5 12 14.5 17.5 7.5" /></svg></span>'
                            : '<span class="inline-flex items-center text-orange-500" aria-label="Sent"><svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 7 14.5 16.5 5.5" /></svg></span>')
                        : '';

                    wrapper.innerHTML = `
                        <div class="max-w-[86%] sm:max-w-[72%]">
                            <div class="rounded-[1.75rem] px-4 py-3 text-sm leading-relaxed shadow-sm ring-1 ${isOwn ? 'bg-orange-500 text-white ring-orange-400/40' : 'bg-white text-slate-800 ring-slate-200'}">
                                ${attachmentsHtml}
                                ${bodyWithSpacing}
                            </div>
                            <div class="mt-1.5 flex items-center gap-1.5 px-1 text-[11px] font-semibold ${isOwn ? 'justify-end text-orange-700' : 'justify-start text-slate-500'}">
                                <span>${this.escapeHtml(String(message.time || 'Now'))}</span>
                                ${tickHtml}
                            </div>
                        </div>
                    `;

                    viewport.appendChild(wrapper);
                    this.lastMessageId = Math.max(this.lastMessageId, Number(message.id));

                    if (shouldScroll) {
                        this.scrollToBottom();
                    }
                },

                normalizeAttachments(message) {
                    if (message && Array.isArray(message.attachments) && message.attachments.length > 0) {
                        return message.attachments;
                    }

                    if (message && message.attachment_url) {
                        return [{
                            url: message.attachment_url,
                            name: message.attachment_name || 'Attachment',
                            mime: message.attachment_mime || 'application/octet-stream',
                            size: message.attachment_size || null,
                            kind: message.attachment_kind || 'file',
                        }];
                    }

                    return [];
                },

                buildAttachmentsHtml(attachments, isOwn) {
                    if (!Array.isArray(attachments) || attachments.length === 0) {
                        return '';
                    }

                    return `<div class="space-y-2">${attachments.map((attachment) => this.buildSingleAttachmentHtml(attachment, isOwn)).join('')}</div>`;
                },

                buildSingleAttachmentHtml(attachment, isOwn) {
                    const url = this.escapeHtml(String(attachment.url || ''));
                    if (url === '') {
                        return '';
                    }

                    const name = this.escapeHtml(String(attachment.name || 'Attachment'));
                    const mime = this.escapeHtml(String(attachment.mime || 'application/octet-stream'));
                    const kind = String(attachment.kind || 'file');

                    if (kind === 'image') {
                        return `
                            <a href="${url}" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl ring-1 ${isOwn ? 'ring-orange-300/70' : 'ring-slate-200'}">
                                <img src="${url}" alt="${name}" class="max-h-72 w-full object-cover">
                            </a>
                        `;
                    }

                    if (kind === 'audio') {
                        return `
                            <audio controls preload="none" class="w-full rounded-xl bg-black/5 p-2">
                                <source src="${url}" type="${mime}">
                            </audio>
                        `;
                    }

                    return `
                        <a href="${url}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold ${isOwn ? 'bg-orange-400/30 text-white' : 'bg-slate-100 text-slate-700'}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 10v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-8" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0-3-3m3 3 3-3" />
                            </svg>
                            <span>${name}</span>
                        </a>
                    `;
                },

                formatBodyText(text) {
                    return this.escapeHtml(String(text || '')).replace(/\n/g, '<br>');
                },

                escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
            };
        }
    </script>
</x-app-layout>

@php
    $currentUser = auth()->user();
    $unreadOnPage = (int) $conversations->sum('unread_count');
@endphp

<x-app-layout>
    <div class="-mx-4 -my-5 bg-[radial-gradient(circle_at_top_right,_rgba(56,189,248,0.11),_transparent_38%),radial-gradient(circle_at_bottom_left,_rgba(251,146,60,0.16),_transparent_40%),linear-gradient(180deg,_#f8fafc_0%,_#eef2ff_100%)] sm:mx-auto sm:my-0 sm:max-w-4xl sm:rounded-[2rem] sm:border sm:border-slate-200 sm:shadow-sm">
        <section class="sticky top-16 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
            <div class="mx-auto max-w-4xl px-4 py-4 sm:px-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-sky-600">Inbox</p>
                        <h1 class="font-display text-2xl font-black text-slate-900 sm:text-3xl">Messages</h1>
                        <p class="mt-1 text-sm text-slate-600">Follow up with buyers and sellers in one place.</p>
                    </div>
                    <span class="inline-flex rounded-full bg-slate-900 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-white">
                        {{ $unreadOnPage }} unread
                    </span>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-4xl px-3 pb-6 pt-4 sm:px-6 sm:pb-8">
            @if ($conversations->isEmpty())
                <div class="flex min-h-[48vh] items-center justify-center">
                    <div class="max-w-sm rounded-[2rem] border border-dashed border-slate-300 bg-white/90 px-6 py-10 text-center shadow-sm">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-4l-3 3z" />
                            </svg>
                        </div>
                        <p class="mt-4 font-display text-2xl font-black text-slate-900">No conversations yet</p>
                        <p class="mt-2 text-sm text-slate-600">Start chatting from a listing and your inbox will appear here.</p>
                    </div>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($conversations as $conversation)
                        @php
                            $other = $conversation->otherParticipant($currentUser);
                            $otherName = trim((string) ($other?->name ?? 'Unknown User'));
                            $otherInitials = collect(preg_split('/\s+/', $otherName) ?: [])
                                ->filter(fn ($part) => trim((string) $part) !== '')
                                ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
                                ->take(2)
                                ->implode('');
                            $otherInitials = $otherInitials !== '' ? $otherInitials : 'U';
                            $avatarUrl = null;
                            if ($other?->avatar) {
                                $avatarPath = (string) $other->avatar;
                                $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
                                    ? $avatarPath
                                    : '/storage/'.ltrim($avatarPath, '/');
                            }
                            $isOnline = $other?->last_seen_at && $other->last_seen_at->gt(now()->subMinutes(5));
                            $statusText = $isOnline
                                ? 'Online now'
                                : ($other?->last_seen_at ? 'Last active '.$other->last_seen_at->diffForHumans() : 'Last seen unavailable');

                            $lastMessage = $conversation->latestMessage;
                            if (! $lastMessage) {
                                $lastSnippet = 'No messages yet.';
                            } elseif (trim((string) $lastMessage->body) !== '') {
                                $lastSnippet = \Illuminate\Support\Str::limit((string) $lastMessage->body, 110);
                            } elseif (($lastMessage->attachment_kind ?? null) === 'image') {
                                $lastSnippet = 'Sent a photo';
                            } elseif (($lastMessage->attachment_kind ?? null) === 'audio') {
                                $lastSnippet = 'Sent a voice note';
                            } else {
                                $lastSnippet = 'Sent an attachment';
                            }

                            $listingPriceLabel = ($conversation->listing->price_type ?? 'fixed') === 'free'
                                ? 'FREE'
                                : '₹'.number_format((float) $conversation->listing->price);
                        @endphp

                        <a href="{{ route('chat.show', $conversation) }}" class="block rounded-[1.8rem] border border-white/80 bg-white/90 p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md">
                            <div class="flex items-start gap-3">
                                <div class="relative shrink-0">
                                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-sky-100 text-sm font-black uppercase text-sky-700 ring-2 ring-white">
                                        @if ($avatarUrl)
                                            <img src="{{ $avatarUrl }}" alt="{{ $otherName }}" class="h-full w-full object-cover">
                                        @else
                                            <span>{{ $otherInitials }}</span>
                                        @endif
                                    </div>
                                    <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full border-2 border-white {{ $isOnline ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-display text-lg font-black text-slate-900">{{ $otherName }}</p>
                                            <p class="text-xs font-semibold {{ $isOnline ? 'text-emerald-600' : 'text-slate-500' }}">
                                                {{ $statusText }}
                                            </p>
                                        </div>

                                        <div class="shrink-0 text-right">
                                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                                {{ $conversation->last_message_at?->diffForHumans() ?? $conversation->created_at->diffForHumans() }}
                                            </p>
                                            @if ($conversation->unread_count > 0)
                                                <span class="mt-1 inline-flex rounded-full bg-orange-500 px-2.5 py-1 text-[11px] font-black text-white">
                                                    {{ $conversation->unread_count }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <p class="mt-2 text-sm text-slate-600">{{ $lastSnippet }}</p>

                                    <div class="mt-3 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-2.5 py-2">
                                        <img src="{{ $conversation->listing->main_image_url }}" alt="{{ $conversation->listing->title }}" class="h-12 w-12 rounded-xl object-cover">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-black uppercase tracking-[0.14em] text-orange-600">Related Ad</p>
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $conversation->listing->title }}</p>
                                            <p class="truncate text-xs font-semibold text-slate-500">{{ $listingPriceLabel }} • {{ $conversation->listing->city }}</p>
                                        </div>
                                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="px-1 pt-3">
                    {{ $conversations->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>

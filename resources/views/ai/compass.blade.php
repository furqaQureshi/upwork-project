<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold text-slate-900">AI Assistant</h1>
                <p class="text-sm text-slate-600">Conversational property search powered by AI</p>
            </div>
            <a href="{{ route('home') }}" class="app-btn-muted">Back to Home</a>
        </div>
    </x-slot>

    <div class="grid gap-5 lg:grid-cols-3" x-data="compassGptPage()">
        <section class="lg:col-span-2 space-y-4">
            <div class="app-card space-y-3">
                <p class="text-sm text-slate-600">Try natural requests like: <span class="font-semibold">2BHK in Gaya under 15 lakh near station</span></p>
                <textarea x-model="query" rows="3" class="app-textarea" placeholder="Describe your ideal property in natural language..."></textarea>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="askCompass()" :disabled="loading || query.trim() === ''" class="app-btn-primary disabled:opacity-60">
                        <span x-show="!loading">Ask AI Assistant</span>
                        <span x-show="loading" x-cloak>Analyzing...</span>
                    </button>
                    <button type="button" @click="clearChat()" class="app-btn-muted">Clear</button>
                </div>

                <p x-show="error" x-text="error" x-cloak class="text-sm font-semibold text-rose-600"></p>
            </div>

            <div class="app-card" x-show="assistantSummary" x-cloak>
                <h2 class="font-display text-lg font-bold text-slate-900">AI Summary</h2>
                <p class="mt-2 text-sm text-slate-700" x-text="assistantSummary"></p>
                <p class="mt-3 text-xs font-semibold text-orange-700" x-show="clarifyingQuestion" x-text="clarifyingQuestion"></p>
            </div>

            <div class="space-y-3" x-show="recommendations.length > 0" x-cloak>
                <template x-for="item in recommendations" :key="item.listing_id">
                    <article class="app-card">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-display text-lg font-bold text-slate-900" x-text="item.title"></h3>
                                <p class="text-sm text-slate-500"><span x-text="item.city"></span><span x-show="item.state">, <span x-text="item.state"></span></span></p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700">₹<span x-text="formatMoney(item.price)"></span></span>
                        </div>

                        <p class="mt-2 text-sm text-slate-700" x-text="item.summary"></p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Pros</p>
                                <ul class="mt-2 list-disc space-y-1 pl-4 text-xs text-emerald-800">
                                    <template x-for="pro in item.pros" :key="pro">
                                        <li x-text="pro"></li>
                                    </template>
                                    <li x-show="!item.pros || item.pros.length === 0">Balanced market fit</li>
                                </ul>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Trade-offs</p>
                                <ul class="mt-2 list-disc space-y-1 pl-4 text-xs text-amber-800">
                                    <template x-for="tradeoff in item.tradeoffs" :key="tradeoff">
                                        <li x-text="tradeoff"></li>
                                    </template>
                                    <li x-show="!item.tradeoffs || item.tradeoffs.length === 0">Request more seller details before final decision</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-3">
                            <a :href="item.listing_url" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">View Listing</a>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <aside class="space-y-4">
            <div class="app-card">
                <h2 class="font-display text-lg font-bold text-slate-900">How AI Assistant Works</h2>
                <ul class="mt-3 list-disc space-y-2 pl-4 text-sm text-slate-600">
                    <li>Understands natural language intent (budget, BHK, locality, preferences).</li>
                    <li>Asks clarifying questions when criteria are incomplete.</li>
                    <li>Returns ranked listings with concise pros and trade-offs.</li>
                </ul>
            </div>
        </aside>
    </div>

    <script>
        function compassGptPage() {
            return {
                query: '',
                loading: false,
                error: '',
                assistantSummary: '',
                clarifyingQuestion: '',
                recommendations: [],
                history: [],

                async askCompass() {
                    if (this.loading || this.query.trim() === '') {
                        return;
                    }

                    this.loading = true;
                    this.error = '';

                    try {
                        const response = await fetch('{{ route('ai.compass.chat') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                query: this.query,
                                history: this.history,
                            }),
                        });

                        const payload = await response.json();

                        if (!response.ok || !payload.ok) {
                            this.error = payload.message || 'Unable to process AI Assistant request right now.';
                            return;
                        }

                        const data = payload.data || {};

                        this.assistantSummary = data.summary || '';
                        this.clarifyingQuestion = data.clarifying_question || '';
                        this.recommendations = Array.isArray(data.recommendations) ? data.recommendations : [];

                        this.history.push({ role: 'user', content: this.query });
                        this.history.push({ role: 'assistant', content: this.assistantSummary });

                        if (this.history.length > 10) {
                            this.history = this.history.slice(this.history.length - 10);
                        }
                    } catch (_) {
                        this.error = 'AI Assistant request failed. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                },

                clearChat() {
                    this.query = '';
                    this.error = '';
                    this.assistantSummary = '';
                    this.clarifyingQuestion = '';
                    this.recommendations = [];
                    this.history = [];
                },

                formatMoney(value) {
                    const number = Number(value || 0);
                    return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(number);
                },
            };
        }
    </script>
</x-app-layout>

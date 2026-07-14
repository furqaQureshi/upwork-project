<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold text-slate-900">AI Navigator</h1>
                <p class="text-sm text-slate-600">CV parsing, skill extraction, and smart job matchmaking</p>
            </div>
            <a href="{{ route('home') }}" class="app-btn-muted">Back to Home</a>
        </div>
    </x-slot>

    <div class="grid gap-5 lg:grid-cols-3" x-data="aiNavigatorPage()">
        <section class="lg:col-span-2 space-y-4">
            <div class="app-card space-y-3">
                <label class="settings-label" for="cv_text">Paste CV text</label>
                <textarea id="cv_text" x-model="cvText" rows="8" class="app-textarea" placeholder="Paste resume profile, skills, and experience summary..."></textarea>

                <div>
                    <label class="settings-label" for="cv_file">Or upload CV file</label>
                    <input id="cv_file" x-ref="cvFile" type="file" accept=".txt,.md,.csv,.docx,.pdf" class="app-input mt-1">
                    <p class="mt-1 text-xs text-slate-500">Supported: txt, md, csv, docx, pdf (best results with text-based CVs).</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="runMatch()" :disabled="loading" class="app-btn-primary disabled:opacity-60">
                        <span x-show="!loading">Run AI Match</span>
                        <span x-show="loading" x-cloak>Matching...</span>
                    </button>
                    <button type="button" @click="clearResult()" class="app-btn-muted">Reset</button>
                </div>

                <p x-show="error" x-text="error" x-cloak class="text-sm font-semibold text-rose-600"></p>
            </div>

            <div class="app-card" x-show="summary" x-cloak>
                <h2 class="font-display text-lg font-bold text-slate-900">AI Navigator Summary</h2>
                <p class="mt-2 text-sm text-slate-700" x-text="summary"></p>

                <div class="mt-3 flex flex-wrap gap-1.5" x-show="keywords.length > 0">
                    <template x-for="keyword in keywords" :key="keyword">
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700" x-text="keyword"></span>
                    </template>
                </div>
            </div>

            <div class="space-y-3" x-show="matches.length > 0" x-cloak>
                <template x-for="item in matches" :key="item.listing_id">
                    <article class="app-card">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-display text-lg font-bold text-slate-900" x-text="item.title"></h3>
                                <p class="text-sm text-slate-500"><span x-text="item.city"></span><span x-show="item.state">, <span x-text="item.state"></span></span></p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700">
                                Match <span x-text="item.match_score"></span>%
                            </span>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Matched Skills</p>
                                <ul class="mt-2 list-disc space-y-1 pl-4 text-xs text-emerald-800">
                                    <template x-for="skill in item.matched_keywords" :key="skill">
                                        <li x-text="skill"></li>
                                    </template>
                                    <li x-show="!item.matched_keywords || item.matched_keywords.length === 0">General profile fit</li>
                                </ul>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Missing Keywords</p>
                                <ul class="mt-2 list-disc space-y-1 pl-4 text-xs text-amber-800">
                                    <template x-for="skill in item.missing_keywords" :key="skill">
                                        <li x-text="skill"></li>
                                    </template>
                                    <li x-show="!item.missing_keywords || item.missing_keywords.length === 0">Strong alignment across required keywords</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-3">
                            <a :href="item.listing_url" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">View Job Listing</a>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <aside class="space-y-4">
            <div class="app-card">
                <h2 class="font-display text-lg font-bold text-slate-900">What It Does</h2>
                <ul class="mt-3 list-disc space-y-2 pl-4 text-sm text-slate-600">
                    <li>Parses CV keywords from skills, tools, and experience text.</li>
                    <li>Ranks jobs by semantic overlap and practical fit score.</li>
                    <li>Provides proactive guidance on missing profile keywords.</li>
                </ul>
            </div>
        </aside>
    </div>

    <script>
        function aiNavigatorPage() {
            return {
                cvText: '',
                loading: false,
                error: '',
                summary: '',
                keywords: [],
                matches: [],

                async runMatch() {
                    if (this.loading) {
                        return;
                    }

                    this.loading = true;
                    this.error = '';

                    try {
                        const formData = new FormData();
                        if (this.cvText.trim() !== '') {
                            formData.append('cv_text', this.cvText.trim());
                        }

                        const fileInput = this.$refs.cvFile;
                        if (fileInput && fileInput.files && fileInput.files[0]) {
                            formData.append('cv_file', fileInput.files[0]);
                        }

                        const response = await fetch('{{ route('ai.jobs.cv-match') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: formData,
                        });

                        const payload = await response.json();
                        if (!response.ok || !payload.ok) {
                            this.error = payload.message || 'Unable to run CV matching right now.';
                            return;
                        }

                        const data = payload.data || {};
                        this.summary = data.navigator_summary || '';
                        this.keywords = Array.isArray(data.keywords) ? data.keywords : [];
                        this.matches = Array.isArray(data.matches) ? data.matches : [];
                    } catch (_) {
                        this.error = 'CV matching request failed. Please retry.';
                    } finally {
                        this.loading = false;
                    }
                },

                clearResult() {
                    this.cvText = '';
                    this.error = '';
                    this.summary = '';
                    this.keywords = [];
                    this.matches = [];
                    if (this.$refs.cvFile) {
                        this.$refs.cvFile.value = '';
                    }
                },
            };
        }
    </script>
</x-app-layout>

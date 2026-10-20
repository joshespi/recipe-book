<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $recipe->title.' - cook mode'])
        @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100"
          x-data="cookMode(@js(['steps' => $steps, 'ingredientCount' => collect($sections)->sum(fn ($s) => $s['lines']->count())]))"
          x-on:keydown.arrow-right.window="next()"
          x-on:keydown.arrow-left.window="prev()">

        <header class="sticky top-0 z-10 border-b border-stone-200 bg-white/90 backdrop-blur dark:border-stone-800 dark:bg-stone-900/90">
            <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3">
                <a href="{{ $backUrl }}" class="btn-secondary px-3 py-1.5">← Exit</a>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-semibold">{{ $recipe->title }}</h1>
                    @if (abs($factor - 1) > 0.0005)
                        <p class="text-xs text-red-700 dark:text-red-400">Scaled {{ \App\Support\Quantity::format($factor) }}×</p>
                    @endif
                </div>
                <span x-show="awake" x-cloak class="hidden text-xs text-stone-500 sm:inline" title="The screen will stay on while you cook">☀ Screen on</span>
            </div>

            <nav class="mx-auto flex max-w-3xl gap-1 px-4 pb-2" aria-label="Cook mode sections">
                <button type="button" x-on:click="tab = 'steps'" :aria-pressed="tab === 'steps'"
                        class="rounded-lg px-4 py-1.5 text-sm font-medium"
                        :class="tab === 'steps' ? 'bg-red-600 text-white' : 'bg-stone-100 dark:bg-stone-800'">Steps</button>
                <button type="button" x-on:click="tab = 'ingredients'" :aria-pressed="tab === 'ingredients'"
                        class="rounded-lg px-4 py-1.5 text-sm font-medium"
                        :class="tab === 'ingredients' ? 'bg-red-600 text-white' : 'bg-stone-100 dark:bg-stone-800'">
                    Ingredients <span class="opacity-75" x-text="'(' + checkedCount + '/' + ingredientCount + ')'"></span>
                </button>
            </nav>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-6 pb-40">
            <section x-show="tab === 'ingredients'" x-cloak aria-label="Ingredients">
                @foreach ($sections as $section)
                    @if ($section['name'])
                        <h2 class="mt-6 mb-2 text-lg font-semibold text-red-800 first:mt-0 dark:text-red-400">{{ $section['name'] }}</h2>
                    @endif
                    <ul class="space-y-1">
                        @foreach ($section['lines'] as $line)
                            @php($key = $loop->parent->index.'-'.$loop->index)
                            <li>
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg p-3 text-xl hover:bg-stone-100 dark:hover:bg-stone-900">
                                    <input type="checkbox" class="mt-1.5 size-5 shrink-0 rounded border-stone-400 text-red-600 focus:ring-red-500"
                                           x-on:change="toggleIngredient('{{ $key }}')" :checked="checked['{{ $key }}']" />
                                    <x-ingredient-line :line="$line" ::class="checked['{{ $key }}'] && 'text-stone-400 line-through dark:text-stone-600'" />
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </section>

            <section x-show="tab === 'steps'" aria-label="Steps">
                @if ($steps->isEmpty())
                    <p class="text-stone-500">This recipe has no steps yet.</p>
                @else
                    <div class="mb-4 h-2 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800" role="progressbar" :aria-valuenow="i + 1" aria-valuemin="1" :aria-valuemax="steps.length">
                        <div class="h-full bg-red-500 transition-all" :style="'width:' + ((i + 1) / steps.length * 100) + '%'"></div>
                    </div>
                    <p class="mb-2 text-sm font-medium text-stone-500">Step <span x-text="i + 1"></span> of <span x-text="steps.length"></span></p>

                    <p class="text-2xl leading-relaxed whitespace-pre-line sm:text-3xl" x-text="steps[i].body" aria-live="polite"></p>

                    <template x-if="steps[i].timer">
                        <div class="mt-6">
                            <button type="button" class="btn-primary px-5 py-3 text-lg" x-on:click="startTimer(i)" x-show="!timers[i] || timers[i].state === 'idle'">
                                ⏲ Start <span x-text="steps[i].timer"></span> min timer
                            </button>
                        </div>
                    </template>
                @endif
            </section>
        </main>

        {{-- Running timers stay visible while you move between steps --}}
        <div class="fixed inset-x-0 bottom-20 z-20 mx-auto flex max-w-3xl flex-col gap-2 px-4" x-show="Object.keys(timers).length" x-cloak>
            <template x-for="(t, idx) in timers" :key="idx">
                <div x-show="t.state !== 'idle'" class="card flex items-center gap-3 p-3 shadow-lg" :class="t.state === 'done' && 'border-red-500 bg-red-50 dark:bg-red-950'">
                    <span class="text-sm font-medium">Step <span x-text="Number(idx) + 1"></span></span>
                    <span class="font-mono text-2xl font-semibold tabular-nums" x-text="t.state === 'done' ? 'Time is up!' : format(t.remaining)"></span>
                    <button type="button" class="btn-secondary ml-auto px-3 py-1.5" x-on:click="dismissTimer(idx)" x-text="t.state === 'done' ? 'Dismiss' : 'Cancel'"></button>
                </div>
            </template>
        </div>

        @if ($steps->isNotEmpty())
            <footer class="fixed inset-x-0 bottom-0 z-10 border-t border-stone-200 bg-white/95 backdrop-blur dark:border-stone-800 dark:bg-stone-900/95" x-show="tab === 'steps'">
                <div class="mx-auto flex max-w-3xl gap-3 px-4 py-3">
                    <button type="button" class="btn-secondary flex-1 py-4 text-lg" x-on:click="prev()" :disabled="i === 0">← Back</button>
                    <button type="button" class="btn-primary flex-1 py-4 text-lg" x-on:click="next()" x-show="i < steps.length - 1">Next →</button>
                    <a href="{{ $backUrl }}" class="btn-primary flex-1 py-4 text-lg" x-show="i === steps.length - 1" x-cloak>Done 🎉</a>
                </div>
            </footer>
        @endif

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('cookMode', (config) => ({
                    steps: config.steps,
                    ingredientCount: config.ingredientCount,
                    tab: 'steps',
                    i: 0,
                    checked: {},
                    timers: {},
                    awake: false,
                    wakeLock: null,
                    audio: null,

                    get checkedCount() { return Object.values(this.checked).filter(Boolean).length },

                    init() {
                        this.requestWakeLock();
                        document.addEventListener('visibilitychange', () => {
                            if (document.visibilityState === 'visible') this.requestWakeLock();
                        });
                    },

                    async requestWakeLock() {
                        try {
                            if ('wakeLock' in navigator) {
                                this.wakeLock = await navigator.wakeLock.request('screen');
                                this.awake = true;
                                this.wakeLock.addEventListener('release', () => { this.awake = false });
                            }
                        } catch (e) { this.awake = false }
                    },

                    next() { if (this.tab === 'steps' && this.i < this.steps.length - 1) this.i++ },
                    prev() { if (this.tab === 'steps' && this.i > 0) this.i-- },
                    toggleIngredient(key) { this.checked[key] = !this.checked[key] },

                    format(seconds) {
                        const m = Math.floor(seconds / 60), s = seconds % 60;
                        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                    },

                    startTimer(index) {
                        // Creating the AudioContext inside this click lets the alarm play later.
                        try { this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)() } catch (e) {}
                        const end = Date.now() + this.steps[index].timer * 60000;
                        this.timers = { ...this.timers, [index]: { state: 'running', remaining: this.steps[index].timer * 60, handle: null } };
                        // Update through the reactive copy so the countdown redraws.
                        const timer = this.timers[index];
                        timer.handle = setInterval(() => {
                            timer.remaining = Math.max(0, Math.round((end - Date.now()) / 1000));
                            if (timer.remaining <= 0) { clearInterval(timer.handle); timer.state = 'done'; this.alarm() }
                        }, 500);
                    },

                    dismissTimer(index) {
                        if (this.timers[index]) clearInterval(this.timers[index].handle);
                        const copy = { ...this.timers };
                        delete copy[index];
                        this.timers = copy;
                    },

                    alarm() {
                        try { navigator.vibrate && navigator.vibrate([300, 150, 300, 150, 300]) } catch (e) {}
                        if (!this.audio) return;
                        [0, 0.35, 0.7].forEach((delay) => {
                            const osc = this.audio.createOscillator(), gain = this.audio.createGain();
                            osc.frequency.value = 880; osc.connect(gain); gain.connect(this.audio.destination);
                            const t = this.audio.currentTime + delay;
                            gain.gain.setValueAtTime(0.2, t); gain.gain.exponentialRampToValueAtTime(0.001, t + 0.25);
                            osc.start(t); osc.stop(t + 0.3);
                        });
                    },
                }));
            });
        </script>

        @livewireScripts
        @fluxScripts
    </body>
</html>

<div
    class="print:hidden"
    x-data="itoPanel({{ request()->is('backoffice/assistant*') ? 'true' : 'false' }})"
>
    <section
        x-show="open"
        x-cloak
        class="fixed bottom-20 right-4 z-40 flex w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:right-5"
    >
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <div>
                <p class="text-sm font-bold text-slate-950">Ito</p>
                <p class="text-[11px] text-slate-500">Ica Taufik assistant</p>
            </div>
            <button type="button" @click="open = false" class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100" aria-label="Tutup Ito">Tutup</button>
        </header>
        <div class="max-h-80 space-y-3 overflow-y-auto px-4 py-3" x-ref="thread">
            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.role === 'user' ? 'text-right' : ''">
                    <div
                        class="inline-block max-w-full rounded-2xl px-3 py-2 text-left text-xs whitespace-pre-line"
                        :class="message.role === 'user' ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-800'"
                        x-text="message.text"
                    ></div>
                    <div x-show="message.links.length" class="mt-1.5 flex flex-wrap gap-1.5" :class="message.role === 'user' ? 'justify-end' : ''">
                        <template x-for="link in message.links" :key="link.url">
                            <a :href="link.url" class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800 hover:bg-sky-100" x-text="link.label"></a>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        <form class="border-t border-slate-100 p-3" @submit.prevent="ask(question)">
            <div class="flex gap-2">
                <input
                    x-model="question"
                    type="text"
                    maxlength="500"
                    placeholder="Tanya Ito..."
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                >
                <button type="submit" :disabled="busy" class="rounded-xl bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-50">Tanya</button>
            </div>
            <p x-show="error" x-cloak class="mt-2 text-[11px] text-rose-600" x-text="error"></p>
        </form>
    </section>

    <button
        type="button"
        @click="open = !open"
        class="fixed bottom-4 right-4 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-slate-950 text-white shadow-lg ring-2 ring-white transition hover:bg-sky-700 sm:bottom-5 sm:right-5"
        :aria-expanded="open"
        aria-label="Ito, Ica Taufik assistant"
        title="Ito"
    >
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l1.6 4.2L18 9l-4.4 1.8L12 15l-1.6-4.2L6 9l4.4-1.8L12 3z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 14l.7 1.8L20.5 16.5 18.7 17.2 18 19l-.7-1.8L15.5 16.5l1.8-.7L18 14z"/>
        </svg>
    </button>
</div>

<script>
    function itoPanel(startOpen) {
        return {
            open: startOpen,
            question: '',
            busy: false,
            error: '',
            messages: [{
                role: 'assistant',
                text: 'Saya Ito, Ica Taufik assistant. Tanya data cabang Anda atau cara memakai aplikasi. Saya hanya membaca, lalu menunjukkan halamannya.',
                links: [],
            }],

            async ask(text) {
                const question = (text || '').trim();
                if (!question || this.busy) {
                    return;
                }

                this.error = '';
                this.open = true;
                this.messages.push({ role: 'user', text: question, links: [] });
                this.question = '';
                this.busy = true;

                try {
                    const response = await fetch(@js(route('backoffice.assistant.ask')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ question }),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        this.error = payload.message || 'Pertanyaan tidak bisa diproses.';
                        return;
                    }

                    this.messages.push({
                        role: 'assistant',
                        text: payload.answer,
                        links: payload.links || [],
                    });
                    this.$nextTick(() => {
                        this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight;
                    });
                } catch (error) {
                    this.error = 'Gangguan koneksi. Silakan coba lagi.';
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>

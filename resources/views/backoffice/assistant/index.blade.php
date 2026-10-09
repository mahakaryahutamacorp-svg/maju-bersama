<x-app-layout title="Asisten" :breadcrumbs="[
    ['label' => 'Asisten'],
]">
    <main class="mx-auto w-full max-w-3xl space-y-6 px-6 py-6 lg:px-10" x-data="assistantPanel()">
        <div>
            <p class="text-sm font-medium text-sky-700">Baca saja</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Asisten aplikasi</h2>
            <p class="mt-2 text-sm text-slate-600">Tanyakan data dan cara pakai aplikasi. Asisten menunjukkan angkanya dan halaman yang tepat, lalu berhenti. Pencatatan tetap dikerjakan sendiri.</p>
        </div>

        <section class="flex min-h-[32rem] flex-col rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="max-h-[32rem] flex-1 space-y-4 overflow-y-auto px-5 py-5" x-ref="thread">
                <template x-for="(message, index) in messages" :key="index">
                    <div :class="message.role === 'user' ? 'ml-10 text-right' : 'mr-10'">
                        <div
                            class="inline-block max-w-full rounded-2xl px-4 py-3 text-left text-sm whitespace-pre-line"
                            :class="message.role === 'user' ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-800'"
                            x-text="message.text"
                        ></div>
                        <div x-show="message.links.length" class="mt-2 flex flex-wrap gap-2" :class="message.role === 'user' ? 'justify-end' : ''">
                            <template x-for="link in message.links" :key="link.url">
                                <a :href="link.url" class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-800 hover:bg-sky-100" x-text="link.label"></a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                <div class="mb-3 flex flex-wrap gap-2">
                    <template x-for="prompt in prompts" :key="prompt">
                        <button type="button" @click="ask(prompt)" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50" x-text="prompt"></button>
                    </template>
                </div>
                <form class="flex gap-2" @submit.prevent="ask(question)">
                    <input
                        x-model="question"
                        type="text"
                        maxlength="500"
                        placeholder="Tanya stok, piutang, penjualan, kas, atau cara pakai..."
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                    >
                    <button type="submit" :disabled="busy" class="rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">Tanya</button>
                </form>
                <p x-show="error" x-cloak class="mt-2 text-xs text-rose-600" x-text="error"></p>
            </div>
        </section>
    </main>

    <x-slot:scripts>
    <script>
        function assistantPanel() {
            return {
                question: '',
                busy: false,
                error: '',
                prompts: [
                    'Di mana menu terima bayaran piutang?',
                    'Apa arti status piutang belum lunas?',
                    'Berapa stok barang?',
                    'Sisa piutang pelanggan',
                    'Penjualan hari ini',
                    'Posisi kas dan bank',
                ],
                messages: [{
                    role: 'assistant',
                    text: 'Silakan tanya data cabang Anda atau cara memakai aplikasi. Saya tidak mencatat pembayaran, tidak mengubah stok, dan tidak membuat jurnal.',
                    links: [],
                }],

                async ask(text) {
                    const question = (text || '').trim();
                    if (!question || this.busy) {
                        return;
                    }

                    this.error = '';
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
    </x-slot:scripts>
</x-app-layout>

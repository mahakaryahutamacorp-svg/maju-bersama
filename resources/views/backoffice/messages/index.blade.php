<x-app-layout title="Pesan Internal" :breadcrumbs="[
    ['label' => 'Pesan Internal'],
]">
    <main
        class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-10 lg:py-10"
        x-data="internalChat(@js($contacts), @js($unreadCounts))"
    >
        <div class="mb-5">
            <p class="text-sm font-medium text-emerald-700">Kanal Anda: {{ $ownChannelName }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Pesan Internal</h2>
            <p class="mt-1 text-sm text-slate-600">Kirim pesan singkat antar cabang dan Pusat. Pesan baru dimuat otomatis setiap 10 detik.</p>
        </div>

        <div class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[18rem_1fr]">
            <aside class="border-b border-slate-200 lg:border-b-0 lg:border-r">
                <p class="border-b border-slate-100 px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">Cabang &amp; Pusat</p>
                <ul class="flex gap-2 overflow-x-auto p-3 lg:block lg:max-h-[65vh] lg:space-y-1 lg:overflow-y-auto">
                    <template x-for="contact in contacts" :key="contact.key">
                        <li class="shrink-0">
                            <button
                                type="button"
                                @click="selectContact(contact)"
                                class="flex w-full items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm transition"
                                :class="selectedBranch && selectedBranch.key === contact.key
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                    : 'border-transparent text-slate-700 hover:bg-slate-50'"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold" x-text="contact.name"></span>
                                    <span class="block truncate text-[11px] text-slate-500" x-text="contact.code ? contact.code : 'Kantor Pusat'"></span>
                                </span>
                                <span
                                    x-show="unreadCounts[contact.key]"
                                    x-cloak
                                    class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                    x-text="unreadCounts[contact.key]"
                                ></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="! contacts.length" class="px-3 py-2 text-sm text-slate-500">Belum ada cabang aktif lain.</li>
                </ul>
            </aside>

            <section class="flex h-[70vh] min-h-[24rem] flex-col">
                <header class="border-b border-slate-100 px-4 py-3">
                    <p class="text-sm font-bold text-slate-950" x-text="selectedBranch ? selectedBranch.name : 'Pilih percakapan'"></p>
                    <p class="text-[11px] text-slate-500" x-show="selectedBranch" x-cloak>Diperbarui otomatis setiap 10 detik</p>
                </header>

                <div x-ref="thread" class="flex-1 space-y-2 overflow-y-auto bg-[#efeae2] px-3 py-4 sm:px-5">
                    <p x-show="! selectedBranch" class="py-10 text-center text-sm text-slate-500">Pilih cabang atau Pusat di sebelah kiri untuk mulai mengobrol.</p>
                    <p x-show="selectedBranch && loading && ! messages.length" x-cloak class="py-10 text-center text-sm text-slate-500">Memuat pesan...</p>
                    <p x-show="selectedBranch && ! loading && ! messages.length" x-cloak class="py-10 text-center text-sm text-slate-500">Belum ada pesan. Sapa duluan.</p>

                    <template x-for="item in messages" :key="item.id">
                        <div class="flex" :class="item.is_mine ? 'justify-end' : 'justify-start'">
                            <div
                                class="relative max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm sm:max-w-[70%]"
                                :class="item.is_mine ? 'rounded-tr-sm bg-[#d9fdd3] text-slate-900' : 'rounded-tl-sm bg-white text-slate-900'"
                            >
                                <p class="mb-0.5 text-[11px] font-semibold" :class="item.is_mine ? 'text-emerald-800' : 'text-sky-700'" x-text="item.sender_name"></p>
                                <p class="whitespace-pre-line break-words" x-text="item.message"></p>
                                <p class="mt-1 text-right text-[10px] text-slate-500" x-text="item.time"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <form class="border-t border-slate-200 bg-slate-50 p-3" @submit.prevent="sendMessage()">
                    <div class="flex items-end gap-2">
                        <textarea
                            x-model="newMessage"
                            @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); sendMessage(); }"
                            :disabled="! selectedBranch"
                            rows="1"
                            maxlength="2000"
                            placeholder="Ketik pesan... (Enter kirim, Shift+Enter baris baru)"
                            class="max-h-32 min-h-[2.5rem] w-full resize-y rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-100"
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="! selectedBranch || sending || ! newMessage.trim()"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        >Kirim</button>
                    </div>
                    <p x-show="error" x-cloak class="mt-2 text-xs text-rose-600" x-text="error"></p>
                </form>
            </section>
        </div>
    </main>

    <x-slot:scripts>
        <script>
            function internalChat(contacts, unreadCounts) {
                const fetchUrl = @js(route('backoffice.messages.fetch'));
                const sendUrl = @js(route('backoffice.messages.send'));
                const pollIntervalMs = 10000;

                return {
                    contacts,
                    unreadCounts,
                    selectedBranch: null,
                    messages: [],
                    newMessage: '',
                    loading: false,
                    sending: false,
                    error: '',
                    pollTimer: null,

                    init() {
                        if (this.contacts.length) {
                            this.selectContact(this.contacts[0]);
                        }

                        this.pollTimer = setInterval(() => {
                            if (! document.hidden) {
                                this.fetchMessages(true);
                            }
                        }, pollIntervalMs);
                    },

                    destroy() {
                        clearInterval(this.pollTimer);
                    },

                    selectContact(contact) {
                        this.selectedBranch = contact;
                        this.messages = [];
                        this.error = '';
                        this.fetchMessages(false);
                    },

                    lastMessageId() {
                        return this.messages.length ? this.messages[this.messages.length - 1].id : 0;
                    },

                    async fetchMessages(silent) {
                        const contact = this.selectedBranch;
                        if (! contact) {
                            return;
                        }

                        const params = new URLSearchParams({ branch_id: contact.branch_id ?? '' });
                        if (silent && this.lastMessageId()) {
                            params.set('after_id', this.lastMessageId());
                        }

                        if (! silent) {
                            this.loading = true;
                        }

                        try {
                            const response = await fetch(`${fetchUrl}?${params}`, {
                                headers: { 'Accept': 'application/json' },
                            });
                            if (! response.ok) {
                                if (! silent) {
                                    this.error = 'Pesan tidak bisa dimuat.';
                                }
                                return;
                            }

                            const payload = await response.json();
                            this.unreadCounts = payload.unread_counts || {};

                            if (this.selectedBranch?.key !== contact.key) {
                                return;
                            }

                            this.appendMessages(payload.messages);
                        } catch (error) {
                            if (! silent) {
                                this.error = 'Gangguan koneksi. Silakan coba lagi.';
                            }
                        } finally {
                            if (! silent) {
                                this.loading = false;
                            }
                        }
                    },

                    appendMessages(incoming) {
                        const knownIds = new Set(this.messages.map((item) => item.id));
                        const fresh = incoming.filter((item) => ! knownIds.has(item.id));
                        if (! fresh.length) {
                            return;
                        }

                        const thread = this.$refs.thread;
                        const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;

                        this.messages.push(...fresh);
                        this.messages.sort((first, second) => first.id - second.id);

                        if (nearBottom || fresh.some((item) => item.is_mine)) {
                            this.scrollToBottom();
                        }
                    },

                    async sendMessage() {
                        const text = this.newMessage.trim();
                        const contact = this.selectedBranch;
                        if (! text || ! contact || this.sending) {
                            return;
                        }

                        this.sending = true;
                        this.error = '';

                        try {
                            const response = await fetch(sendUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({ branch_id: contact.branch_id, message: text }),
                            });
                            const payload = await response.json();

                            if (! response.ok) {
                                this.error = payload.message || 'Pesan gagal dikirim.';
                                return;
                            }

                            this.newMessage = '';
                            if (this.selectedBranch?.key === contact.key) {
                                this.appendMessages([payload.message]);
                            }
                        } catch (error) {
                            this.error = 'Gangguan koneksi. Pesan belum terkirim.';
                        } finally {
                            this.sending = false;
                        }
                    },

                    scrollToBottom() {
                        this.$nextTick(() => {
                            this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight;
                        });
                    },
                };
            }
        </script>
    </x-slot:scripts>
</x-app-layout>

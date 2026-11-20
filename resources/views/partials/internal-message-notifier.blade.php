@auth
    @can('use-internal-messages')
        @php
            $internalUnreadCount = \App\Models\InternalMessage::unreadTotalForUser(auth()->user());
        @endphp
        <div
            x-data
            x-cloak
            x-show="$store.internalMessages.notice"
            class="fixed bottom-4 right-4 z-50 w-[min(20rem,calc(100vw-2rem))] print:hidden"
            role="status"
        >
            <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-white p-3 shadow-lg">
                <span class="mt-0.5 h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-rose-500"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-slate-950">Ada pesan internal baru</p>
                    <a href="{{ route('backoffice.messages.index') }}" class="mt-1 inline-flex text-xs font-semibold text-emerald-700 hover:text-emerald-800">Buka pesan</a>
                </div>
                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-800" @click="$store.internalMessages.notice = false">Tutup</button>
            </div>
        </div>
        <script>
            document.addEventListener('alpine:init', () => {
                if (window.__internalMessageStore) {
                    return;
                }
                window.__internalMessageStore = true;

                Alpine.store('internalMessages', {
                    unread: @json($internalUnreadCount),
                    notice: false,
                    known: @json($internalUnreadCount),
                    baseTitle: '',
                    url: @json(route('backoffice.messages.unread')),
                    timer: null,
                    noticeTimer: null,
                    start() {
                        this.syncTitle();
                        window.addEventListener('internal-messages:unread', (event) => {
                            this.apply(Number(event.detail?.unread || 0));
                        });
                        this.timer = setInterval(() => {
                            if (! document.hidden) {
                                this.refresh();
                            }
                        }, 15000);
                    },
                    apply(next) {
                        const previous = this.known;
                        this.unread = next;
                        this.known = next;
                        this.syncTitle();

                        const onChat = window.location.pathname.indexOf('/backoffice/messages') === 0;
                        if (! onChat && next > previous) {
                            this.notice = true;
                            clearTimeout(this.noticeTimer);
                            this.noticeTimer = setTimeout(() => {
                                this.notice = false;
                            }, 8000);
                        }

                        if (next < 1) {
                            this.notice = false;
                        }
                    },
                    syncTitle() {
                        if (! this.baseTitle) {
                            this.baseTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');
                        }

                        const label = this.unread > 99 ? '99+' : String(this.unread);
                        document.title = this.unread > 0 ? `(${label}) ${this.baseTitle}` : this.baseTitle;
                    },
                    async refresh() {
                        try {
                            const response = await fetch(this.url, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            if (! response.ok) {
                                return;
                            }

                            const payload = await response.json();
                            this.apply(Number(payload.unread || 0));
                        } catch (error) {
                            // Angka terakhir tetap dipakai sampai percobaan berikutnya.
                        }
                    },
                });

                Alpine.store('internalMessages').start();
            });
        </script>
    @endcan
@endauth

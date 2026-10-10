<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InternalMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InternalMessageController extends Controller
{
    private const HISTORY_LIMIT = 100;

    private const PUSAT_KEY = 'pusat';

    public function index(Request $request): View
    {
        $user = $request->user();
        $ownChannel = $this->channelOf($user);

        return view('backoffice.messages.index', [
            'contacts' => $this->contactsFor($ownChannel),
            'unreadCounts' => $this->unreadCounts($ownChannel),
            'ownChannelName' => $ownChannel === null ? 'Pusat' : ($user->branch->name ?? 'Cabang'),
        ]);
    }

    public function fetchMessages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $ownChannel = $this->channelOf($user);
        $targetChannel = $this->targetChannel($validated, $ownChannel);
        $afterId = (int) ($validated['after_id'] ?? 0);

        InternalMessage::query()
            ->betweenChannels($targetChannel, $ownChannel)
            ->unreadFor($ownChannel)
            ->update(['is_read' => true]);

        $conversation = InternalMessage::query()
            ->with('sender:id,name')
            ->betweenChannels($ownChannel, $targetChannel)
            ->limit(self::HISTORY_LIMIT);

        $messages = $afterId > 0
            ? $conversation->where('id', '>', $afterId)->orderBy('id')->get()
            : $conversation->orderByDesc('id')->get()->reverse()->values();

        return response()->json([
            'messages' => $messages->map(fn (InternalMessage $message) => $this->present($message, $ownChannel)),
            'unread_counts' => $this->unreadCounts($ownChannel),
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true),
            ],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = trim($validated['message']);
        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Pesan tidak boleh kosong.']);
        }

        $user = $request->user();
        $ownChannel = $this->channelOf($user);
        $targetChannel = $this->targetChannel($validated, $ownChannel);

        $internalMessage = InternalMessage::create([
            'sender_id' => $user->id,
            'sender_branch_id' => $ownChannel,
            'receiver_branch_id' => $targetChannel,
            'message' => $message,
            'is_read' => false,
        ])->load('sender:id,name');

        return response()->json([
            'message' => $this->present($internalMessage, $ownChannel),
        ], 201);
    }

    /**
     * Kanal pengguna: NULL untuk Pusat, branch_id untuk admin cabang.
     */
    private function channelOf(User $user): ?int
    {
        if ($user->isMaster()) {
            return null;
        }

        abort_if($user->branch_id === null, 403);

        return (int) $user->branch_id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function targetChannel(array $validated, ?int $ownChannel): ?int
    {
        $target = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($target === $ownChannel) {
            throw ValidationException::withMessages([
                'branch_id' => 'Tidak bisa mengirim pesan ke kanal sendiri.',
            ]);
        }

        return $target;
    }

    /**
     * @return list<array{key: string, branch_id: int|null, name: string, code: string|null}>
     */
    private function contactsFor(?int $ownChannel): array
    {
        $contacts = [];

        if ($ownChannel !== null) {
            $contacts[] = ['key' => self::PUSAT_KEY, 'branch_id' => null, 'name' => 'Pusat', 'code' => null];
        }

        Branch::query()
            ->where('is_active', true)
            ->when($ownChannel !== null, fn ($query) => $query->whereKeyNot($ownChannel))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->each(function (Branch $branch) use (&$contacts) {
                $contacts[] = [
                    'key' => (string) $branch->id,
                    'branch_id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ];
            });

        return $contacts;
    }

    /**
     * @return array<string, int>
     */
    private function unreadCounts(?int $ownChannel): array
    {
        return InternalMessage::query()
            ->unreadFor($ownChannel)
            ->selectRaw('sender_branch_id, COUNT(*) as total')
            ->groupBy('sender_branch_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->sender_branch_id === null ? self::PUSAT_KEY : (string) $row->sender_branch_id) => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InternalMessage $message, ?int $ownChannel): array
    {
        $senderChannel = $message->sender_branch_id === null ? null : (int) $message->sender_branch_id;

        return [
            'id' => $message->id,
            'message' => $message->message,
            'sender_name' => $message->sender->name ?? 'Pengguna',
            'is_mine' => $senderChannel === $ownChannel,
            'is_read' => $message->is_read,
            'time' => $message->created_at?->format('d/m H:i'),
        ];
    }
}

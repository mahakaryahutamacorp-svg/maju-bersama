<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kanal percakapan diwakili branch_id; NULL berarti Pusat.
 */
class InternalMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'sender_branch_id',
        'receiver_branch_id',
        'message',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function senderBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'sender_branch_id')->withTrashed();
    }

    public function receiverBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'receiver_branch_id')->withTrashed();
    }

    public function scopeBetweenChannels(Builder $query, ?int $firstChannel, ?int $secondChannel): Builder
    {
        return $query->where(function (Builder $conversation) use ($firstChannel, $secondChannel) {
            $conversation
                ->where(fn (Builder $outgoing) => self::whereChannel(
                    self::whereChannel($outgoing, 'sender_branch_id', $firstChannel),
                    'receiver_branch_id',
                    $secondChannel,
                ))
                ->orWhere(fn (Builder $incoming) => self::whereChannel(
                    self::whereChannel($incoming, 'sender_branch_id', $secondChannel),
                    'receiver_branch_id',
                    $firstChannel,
                ));
        });
    }

    public function scopeUnreadFor(Builder $query, ?int $channel): Builder
    {
        return self::whereChannel($query, 'receiver_branch_id', $channel)->where('is_read', false);
    }

    private static function whereChannel(Builder $query, string $column, ?int $channel): Builder
    {
        return $channel === null ? $query->whereNull($column) : $query->where($column, $channel);
    }
}

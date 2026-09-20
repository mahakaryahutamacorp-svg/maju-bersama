<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = [
        'reference_number',
        'source_branch_id',
        'destination_branch_id',
        'created_by',
        'transfer_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->sourceBranch();
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->destinationBranch();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->creator();
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }
}

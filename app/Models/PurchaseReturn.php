<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'goods_receipt_id',
        'return_date',
        'reference_number',
        'total_amount',
        'status',
        'notes',
        'journal_header_id',
    ];

    protected function casts(): array
    {
        return [
            'return_date'  => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}

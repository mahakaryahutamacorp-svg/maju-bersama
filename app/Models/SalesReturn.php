<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'sale_id',
        'user_id',
        'cash_register_shift_id',
        'customer_name',
        'return_date',
        'reference_number',
        'refund_method',
        'chart_of_account_id',
        'total_amount',
        'total_cost',
        'status',
        'reason',
        'journal_header_id',
    ];

    protected function casts(): array
    {
        return [
            'return_date'  => 'date',
            'total_amount' => 'decimal:2',
            'total_cost'   => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegisterShift(): BelongsTo
    {
        return $this->belongsTo(CashRegisterShift::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}

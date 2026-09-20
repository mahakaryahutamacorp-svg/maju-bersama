<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'user_id',
        'supplier_id',
        'chart_of_account_id',
        'payment_date',
        'payment_method',
        'amount',
        'reference_number',
        'notes',
        'journal_header_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount'       => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class);
    }
}

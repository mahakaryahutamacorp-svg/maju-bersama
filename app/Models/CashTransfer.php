<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransfer extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'from_account_id',
        'to_account_id',
        'amount',
        'transfer_date',
        'reference_number',
        'notes',
        'journal_header_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'to_account_id');
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }
}

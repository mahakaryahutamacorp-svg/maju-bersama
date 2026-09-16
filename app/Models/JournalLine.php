<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_header_id',
        'chart_of_account_id',
        'debit',
        'credit',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function account(): BelongsTo
    {
        return $this->chartOfAccount();
    }
}

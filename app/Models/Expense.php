<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'expense_category_id',
        'account_id',
        'amount',
        'expense_date',
        'reference_number',
        'notes',
        'journal_header_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function category(): BelongsTo
    {
        return $this->expenseCategory();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->account();
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class);
    }
}

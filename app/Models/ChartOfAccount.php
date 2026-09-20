<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function expenseCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class, 'account_id');
    }

    public function journalLines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function outgoingTransfers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashTransfer::class, 'from_account_id');
    }

    public function incomingTransfers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashTransfer::class, 'to_account_id');
    }
}

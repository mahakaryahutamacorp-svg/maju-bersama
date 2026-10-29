<?php

namespace App\Models;

use App\Enums\CashFlowTag;
use App\Enums\NormalBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes;

    /** Kas (1110) dan Bank (1120) saja; piutang, uang muka, PPN, dan persediaan bukan uang tunai. */
    public const CASH_BANK_CODES = ['1110', '1120'];

    protected $fillable = [
        'entity_id',
        'code',
        'name',
        'type',
        'is_active',
        'parent_category_id',
        'level',
        'normal_balance',
        'is_postable',
        'is_contra',
        'is_system',
        'branch_id',
        'cash_register_id',
        'cash_flow_tag',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'level' => 'integer',
            'normal_balance' => NormalBalance::class,
            'is_postable' => 'boolean',
            'is_contra' => 'boolean',
            'is_system' => 'boolean',
            'cash_flow_tag' => CashFlowTag::class,
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_category_id');
    }

    public function scopeCashAndBank(Builder $query): Builder
    {
        return $query->where('type', 'asset')
            ->where('is_active', true)
            ->whereIn('code', self::CASH_BANK_CODES);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'account_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(CashTransfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(CashTransfer::class, 'to_account_id');
    }
}

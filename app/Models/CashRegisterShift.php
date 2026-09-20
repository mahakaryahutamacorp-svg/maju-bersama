<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterShift extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'cash_register_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_balance',
        'expected_closing_balance',
        'actual_closing_balance',
        'difference',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'                => 'datetime',
            'closed_at'                => 'datetime',
            'opening_balance'          => 'decimal:2',
            'expected_closing_balance' => 'decimal:2',
            'actual_closing_balance'   => 'decimal:2',
            'difference'               => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}

<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasBranchScope;

    public const HISTORY_DELETE_MESSAGE = 'Gagal: Data ini tidak dapat dihapus karena sudah memiliki riwayat transaksi.';

    protected $fillable = [
        'branch_id',
        'name',
        'phone',
        'email',
        'address',
        'customer_group_id',
    ];

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Sales include tempo receivables, and payments cover receivable settlements.
     * Branch scope is bypassed so history in another branch still blocks deletion.
     */
    public function hasTransactionHistory(): bool
    {
        return $this->sales()->withoutGlobalScopes()->exists()
            || $this->payments()->withoutGlobalScopes()->exists();
    }
}

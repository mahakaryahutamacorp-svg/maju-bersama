<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'customer_group_id',
        'cash_register_shift_id',
        'receipt_number',
        'total_amount',
        'subtotal_non_tax',
        'dpp_taxable',
        'tax_total',
        'discount_amount',
        'paid_amount',
        'payment_status',
        'payment_method',
        'status',
        'due_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'subtotal_non_tax' => 'decimal:2',
            'dpp_taxable' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashRegisterShift(): BelongsTo
    {
        return $this->belongsTo(CashRegisterShift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->creator();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Alasan nota tidak boleh diubah atau dihapus. Null berarti master masih boleh mengoreksinya.
     */
    public function correctionLockReason(): ?string
    {
        $returnCount = array_key_exists('sales_returns_count', $this->attributes)
            ? (int) $this->sales_returns_count
            : $this->salesReturns()->count();

        if ($returnCount > 0) {
            return 'Nota ini sudah punya retur penjualan. Hapus retur dulu sebelum mengubah atau membatalkan nota.';
        }

        $paymentCount = array_key_exists('payment_allocations_count', $this->attributes)
            ? (int) $this->payment_allocations_count
            : $this->paymentAllocations()->count();

        if ($paymentCount > 0) {
            return 'Nota ini sudah ada pelunasan. Batalkan pembayaran dulu sebelum mengubah atau membatalkan nota.';
        }

        if (in_array(strtolower((string) $this->payment_method), ['tempo', 'piutang', 'kredit'], true)
            && bccomp((string) ($this->paid_amount ?? '0'), '0.00', 2) === 1) {
            return 'Piutang nota ini sudah ada pembayaran. Batalkan pembayaran dulu sebelum mengubah atau membatalkan nota.';
        }

        $itemCount = $this->relationLoaded('items')
            ? $this->items->count()
            : $this->items()->count();

        if ($itemCount === 0) {
            return 'Nota ini tidak berisi barang, jadi stok tidak bisa dikembalikan dari sini.';
        }

        return null;
    }
}

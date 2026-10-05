<?php

namespace App\Models;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasBranchScope, HasFactory;

    protected $fillable = [
        'branch_id',
        'distributor_id',
        'invoice_number',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    protected $appends = [
        'remaining_debt',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function purchasePayments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'distributor_id');
    }

    /**
     * Accessor untuk menghitung sisa hutang dagang.
     */
    public function getRemainingDebtAttribute(): float
    {
        $total = (float) $this->total_amount;
        $paid = (float) $this->paid_amount;

        return max(0.0, round($total - $paid, 2));
    }

    /**
     * Update paid_amount and status based on recorded payments.
     */
    public function recalculatePaymentStatus(): void
    {
        $totalPaid = (float) $this->payments()->sum('amount');
        $totalAmount = (float) $this->total_amount;

        $this->paid_amount = $totalPaid;

        if ($totalPaid >= $totalAmount) {
            $this->status = 'paid';
        } elseif ($totalPaid > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'unpaid';
        }

        $this->save();
    }
}

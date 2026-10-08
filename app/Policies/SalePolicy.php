<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Determine whether the user can view any sales.
     */
    public function viewAny(User $user): bool
    {
        return $user->isMaster() || $user->isBranchAdmin();
    }

    /**
     * Determine whether the user can view the specific sale.
     */
    public function view(User $user, Sale $sale): bool
    {
        if ($user->isMaster()) {
            return true;
        }

        return (int) $user->branch_id === (int) $sale->branch_id;
    }

    /**
     * Determine whether the user can create sales (POS).
     */
    public function create(User $user): bool
    {
        return true;
    }
}

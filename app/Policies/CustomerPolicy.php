<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return $user->isMaster() || $user->isBranchAdmin();
    }

    /**
     * Determine whether the user can view the specific customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        if ($user->isMaster()) {
            return true;
        }

        return (int) $user->branch_id === (int) $customer->branch_id;
    }

    /**
     * Determine whether the user can create a customer.
     */
    public function create(User $user): bool
    {
        return $user->isMaster() || $user->isBranchAdmin();
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        if ($user->isMaster()) {
            return true;
        }

        return (int) $user->branch_id === (int) $customer->branch_id;
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->isMaster();
    }
}

<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any expenses.
     */
    public function viewAny(User $user): bool
    {
        return $user->isMaster() || $user->isBranchAdmin();
    }

    /**
     * Determine whether the user can view the specific expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        if ($user->isMaster()) {
            return true;
        }

        return (int) $user->branch_id === (int) $expense->branch_id;
    }

    /**
     * Determine whether the user can create an expense.
     */
    public function create(User $user): bool
    {
        return $user->isMaster() || $user->isBranchAdmin();
    }

    /**
     * Determine whether the user can update the expense.
     */
    public function update(User $user, Expense $expense): bool
    {
        if ($user->isMaster()) {
            return true;
        }

        return (int) $user->branch_id === (int) $expense->branch_id;
    }

    /**
     * Determine whether the user can delete the expense.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $user->isMaster();
    }
}

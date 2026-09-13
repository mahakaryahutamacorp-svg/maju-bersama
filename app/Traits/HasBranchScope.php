<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasBranchScope
{
    /**
     * Boot the model's branch scoping.
     *
     * Applies a global scope that filters results by the authenticated user's
     * branch, unless the user has a "master" role.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('branch', static function (Builder $builder): void {
            if (Auth::check()) {
                $user = Auth::user();

                if (! $user->isMaster()) {
                    $builder->where(
                        $builder->getModel()->qualifyColumn('branch_id'),
                        $user->branch_id
                    );
                }
            }
        });
    }
}

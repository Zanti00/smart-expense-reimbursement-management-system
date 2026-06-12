<?php

namespace App\Modules\CashAdvances\Policies;

use App\Modules\Users\Models\User;
use App\Modules\CashAdvances\Models\CashAdvance;
use Illuminate\Auth\Access\HandlesAuthorization;

class CashAdvancePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any cash advances.
     */
    public function viewAny(User $user)
    {
        // Anyone can view the list (it will be scoped in the controller)
        return true;
    }

    /**
     * Determine whether the user can view the cash advance.
     */
    public function view(User $user, CashAdvance $cashAdvance)
    {
        if ($user->can('serms.cash_advances.manage')) {
            return true;
        }

        return $cashAdvance->user_id === $user->id;
    }

    /**
     * Determine whether the user can create cash advances.
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can approve the cash advance.
     */
    public function approve(User $user, CashAdvance $cashAdvance)
    {
        if (!$user->can('serms.cash_advances.manage')) {
            return false;
        }

        // Cannot approve own cash advance
        if ($cashAdvance->user_id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can reject the cash advance.
     */
    public function reject(User $user, CashAdvance $cashAdvance)
    {
        if (!$user->can('serms.cash_advances.manage')) {
            return false;
        }

        // Cannot reject own cash advance
        if ($cashAdvance->user_id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can disburse the cash advance.
     */
    public function disburse(User $user, CashAdvance $cashAdvance)
    {
        if (!$user->can('serms.cash_advances.manage')) {
            return false;
        }

        // Cannot disburse own cash advance
        if ($cashAdvance->user_id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can acknowledge the cash advance.
     */
    public function acknowledge(User $user, CashAdvance $cashAdvance)
    {
        return $cashAdvance->user_id === $user->id;
    }
}

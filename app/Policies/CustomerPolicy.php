<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Customers are administrative data. Customer users have no access at all --
 * not even to their own customer record -- because the portal never needs it.
 *
 * Every method states its rule explicitly; there is no Gate::before shortcut
 * granting administrators everything, so a new ability added later defaults to
 * denied until someone writes the rule for it.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Deleting customers is out of scope for the MVP -- accounts are
     * deactivated, never destroyed, so history stays intact.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }

    /**
     * Managing (listing, inviting, blocking) the users of a customer.
     */
    public function manageUsers(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }
}

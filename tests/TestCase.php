<?php

namespace Tests;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The password used by every test account. Long enough to satisfy the
     * application's own 12 character minimum.
     */
    protected const PASSWORD = 'correct-horse-battery-staple';

    protected function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create([
            'password' => self::PASSWORD,
            ...$attributes,
        ]);
    }

    protected function customerUser(?Customer $customer = null, array $attributes = []): User
    {
        $customer ??= Customer::factory()->create();

        return User::factory()->for_customer($customer)->create([
            'password' => self::PASSWORD,
            ...$attributes,
        ]);
    }
}

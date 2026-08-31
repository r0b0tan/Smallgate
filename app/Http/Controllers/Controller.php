<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Every controller can call $this->authorize(). Authorisation is never
    // implicit in Smallgate: each action states the ability it requires.
    use AuthorizesRequests;
}

<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Single source of truth for the account password policy, used by register,
 * change-password and reset-password so the rule never drifts between them.
 */
class Passwords
{
    public static function strong(): Password
    {
        return Password::min(10)->mixedCase()->numbers()->symbols();
    }
}

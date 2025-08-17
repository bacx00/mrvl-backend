<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check minimum length (8 characters)
        if (strlen($value) < 8) {
            $fail('The :attribute must be at least 8 characters long.');
            return;
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
            return;
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
            return;
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
            return;
        }

        // Check for at least one special character
        if (!preg_match('/[@$!%*#?&^_\-+=]/', $value)) {
            $fail('The :attribute must contain at least one special character (@$!%*#?&^-_+=).');
            return;
        }

        // Check that password doesn't contain spaces
        if (preg_match('/\s/', $value)) {
            $fail('The :attribute must not contain spaces.');
            return;
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', 'password123', '12345678', '123456789', 'qwerty123',
            'admin123', 'letmein', 'welcome123', 'monkey123', 'dragon123'
        ];
        
        if (in_array(strtolower($value), $weakPasswords)) {
            $fail('The :attribute is too common. Please choose a more unique password.');
            return;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The password must be at least 8 characters and contain uppercase, lowercase, number, and special character.';
    }
}
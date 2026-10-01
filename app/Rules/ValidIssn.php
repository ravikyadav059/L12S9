<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidIssn implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $clean = strtoupper(trim($value));

        // Format validation: 4 digits, hyphen, 3 digits, and a digit or 'X'
        if (! preg_match('/^\d{4}-\d{3}[0-9X]$/', $clean)) {
            $fail('The :attribute must match the valid ISSN format (e.g., 1234-5679 or 1757-790X).');

            return;
        }

        // Checksum calculation (Modulus 11 algorithm with weights 8 through 2)
        $digits = str_replace('-', '', $clean);
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += ((int) $digits[$i]) * (8 - $i);
        }

        $remainder = $sum % 11;
        $check = 11 - $remainder;

        if ($check === 11) {
            $expectedCheckDigit = '0';
        } elseif ($check === 10) {
            $expectedCheckDigit = 'X';
        } else {
            $expectedCheckDigit = (string) $check;
        }

        $actualCheckDigit = $digits[7];

        if ($actualCheckDigit !== $expectedCheckDigit) {
            $fail('The :attribute has an invalid ISSN checksum digit (expected '.$expectedCheckDigit.', but got '.$actualCheckDigit.').');
        }
    }

    /**
     * Static helper to check validity of an ISSN.
     */
    public static function isValid(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $clean = strtoupper(trim($value));
        if (! preg_match('/^\d{4}-\d{3}[0-9X]$/', $clean)) {
            return false;
        }

        $digits = str_replace('-', '', $clean);
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += ((int) $digits[$i]) * (8 - $i);
        }

        $remainder = $sum % 11;
        $check = 11 - $remainder;

        if ($check === 11) {
            $expectedCheckDigit = '0';
        } elseif ($check === 10) {
            $expectedCheckDigit = 'X';
        } else {
            $expectedCheckDigit = (string) $check;
        }

        return $digits[7] === $expectedCheckDigit;
    }
}

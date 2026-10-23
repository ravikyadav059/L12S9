<?php

namespace App\Helpers;

class IssnHelper
{
    /**
     * Normalize and format an ISSN string to standard XXXX-XXXX format.
     */
    public static function format(?string $issn): string
    {
        if (empty($issn)) {
            return '';
        }

        $raw = strtoupper(trim($issn));
        $clean = '';
        for ($i = 0; $i < strlen($raw) && strlen($clean) < 8; $i++) {
            $ch = $raw[$i];
            if (strlen($clean) < 7) {
                if (ctype_digit($ch)) {
                    $clean .= $ch;
                }
            } else {
                if (ctype_digit($ch) || $ch === 'X') {
                    $clean .= $ch;
                }
            }
        }

        if (strlen($clean) > 4) {
            $clean = substr($clean, 0, 4).'-'.substr($clean, 4);
        }

        return $clean;
    }

    /**
     * Validate whether an ISSN string has a valid format (XXXX-XXXX).
     */
    public static function isValid(?string $issn): bool
    {
        if (empty($issn)) {
            return false;
        }

        return (bool) preg_match('/^[0-9]{4}-[0-9]{3}[0-9X]$/i', trim($issn));
    }
}

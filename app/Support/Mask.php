<?php

namespace App\Support;

class Mask
{
    /**
     * Mask an email address, keeping a short prefix and the domain visible.
     */
    public static function email(?string $value): ?string
    {
        if (! $value || ! str_contains($value, '@')) {
            return $value;
        }

        [$local, $domain] = explode('@', $value, 2);

        $visible = min(2, strlen($local));

        return substr($local, 0, $visible).str_repeat('*', 3).'@'.$domain;
    }

    /**
     * Mask a phone number, keeping only the last few digits visible.
     */
    public static function phone(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        $length = strlen($value);
        $visible = min(3, $length);

        return str_repeat('*', max($length - $visible, 0)).substr($value, -$visible);
    }
}

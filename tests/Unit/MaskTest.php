<?php

use App\Support\Mask;

it('masks the local part of an email while keeping the domain visible', function () {
    expect(Mask::email('chisangolaird@gmail.com'))->toBe('ch***@gmail.com');
    expect(Mask::email('al@example.com'))->toBe('al***@example.com');
    expect(Mask::email('a@example.com'))->toBe('a***@example.com');
});

it('returns non-email values and null/empty untouched', function () {
    expect(Mask::email(null))->toBeNull();
    expect(Mask::email(''))->toBe('');
    expect(Mask::email('not-an-email'))->toBe('not-an-email');
});

it('masks all but the last few digits of a phone number', function () {
    expect(Mask::phone('0821234567'))->toBe('*******567');
    expect(Mask::phone('123'))->toBe('123');
});

it('returns null/empty phone values untouched', function () {
    expect(Mask::phone(null))->toBeNull();
    expect(Mask::phone(''))->toBe('');
});

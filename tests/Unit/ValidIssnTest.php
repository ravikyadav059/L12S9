<?php

use App\Rules\ValidIssn;

test('validates correct ISSN format and checksum', function () {
    // Standard valid ISSNs with numeric check digits
    expect(ValidIssn::isValid('1234-5679'))->toBeTrue()
        ->and(ValidIssn::isValid('1234-1258'))->toBeTrue()
        ->and(ValidIssn::isValid('0378-5955'))->toBeTrue()
        ->and(ValidIssn::isValid('0028-0836'))->toBeTrue(); // Nature

    // Valid ISSN with 'X' check digit (10)
    expect(ValidIssn::isValid('1757-790X'))->toBeTrue()
        ->and(ValidIssn::isValid('1757-790x'))->toBeTrue()
        ->and(ValidIssn::isValid('2049-3630'))->toBeTrue();
});

test('rejects format-valid but checksum-invalid ISSNs', function () {
    // 1234-5678 format matches regex but expected check digit is 9
    expect(ValidIssn::isValid('1234-5678'))->toBeFalse();

    // 1757-7909 format matches regex but expected check digit is X
    expect(ValidIssn::isValid('1757-7909'))->toBeFalse();

    // Other checksum mismatches
    expect(ValidIssn::isValid('0000-0001'))->toBeFalse()
        ->and(ValidIssn::isValid('9999-9999'))->toBeFalse();
});

test('rejects invalid ISSN formats', function () {
    expect(ValidIssn::isValid(''))->toBeFalse()
        ->and(ValidIssn::isValid('12345679'))->toBeFalse() // Missing hyphen
        ->and(ValidIssn::isValid('123-45679'))->toBeFalse()
        ->and(ValidIssn::isValid('1234-567'))->toBeFalse()
        ->and(ValidIssn::isValid('ABCD-EFGH'))->toBeFalse()
        ->and(ValidIssn::isValid('1234-567Y'))->toBeFalse();
});

<?php

use Orlyapps\OrlyErrorTracking\Redactor;

it('recognises credentials, tokens and bank data by key', function (string $key, bool $sensitive) {
    expect((new Redactor)->isSensitive($key))->toBe($sensitive);
})->with([
    ['password', true], ['password_confirmation', true], ['_token', true], ['accessToken', true],
    ['login_code', true], ['two_factor_recovery_codes', true], ['iban', true], ['apiKey', true], ['card_number', true],
    ['country_code', false], ['key', false], ['name', false], ['email', false], ['components', false],
]);

it('filters additionally configured keys', function () {
    expect((new Redactor(['birthday']))->isSensitive('Birthday'))->toBeTrue();
});

it('filters credential headers', function () {
    expect((new Redactor)->headers(['cookie' => ['a=b'], 'accept' => ['*/*'], 'x-xsrf-token' => ['y']]))
        ->toBe(['cookie' => '[FILTERED]', 'accept' => '*/*', 'x-xsrf-token' => '[FILTERED]']);
});

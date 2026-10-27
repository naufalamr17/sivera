<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

dataset('sensitive event messages', [
    'plain password' => ['password=do-not-store failed', 'password="[REDACTED]"'],
    'access token' => ['access_token=do-not-store; context=kept', 'access_token="[REDACTED]"; context=kept'],
    'refresh token' => ['refresh_token=do-not-store', 'refresh_token="[REDACTED]"'],
    'client secret' => ['client_secret=do-not-store', 'client_secret="[REDACTED]"'],
    'SMTP password' => ['smtp_password=do-not-store', 'smtp_password="[REDACTED]"'],
    'database password' => ['database_password=do-not-store', 'database_password="[REDACTED]"'],
    'session' => ['session=do-not-store', 'session="[REDACTED]"'],
    'API key' => ['apikey=do-not-store', 'apikey="[REDACTED]"'],
    'cookie' => ['Set-Cookie: do-not-store', 'Set-Cookie: "[REDACTED]"'],
    'wildcard key' => ['my_api_token_value=do-not-store', 'my_api_token_value="[REDACTED]"'],
    'quoted password' => ['password="do-not-store with spaces"; context=kept', 'password="[REDACTED]"; context=kept'],
    'single quotes' => ["'new_password': 'do-not-store with spaces'", "'new_password': \"[REDACTED]\""],
    'JSON' => ['{"password":"do-not-store","password_confirmation":"do-not-store","safe":"kept"}', '{"password":"[REDACTED]","password_confirmation":"[REDACTED]","safe":"kept"}'],
    'escaped JSON quote' => [json_encode(['current_password' => 'do-not-store" with spaces', 'safe' => 'kept'], JSON_THROW_ON_ERROR), '{"current_password":"[REDACTED]","safe":"kept"}'],
    'authorization' => ['AUTHORIZATION: Bearer do-not-store; context=kept', 'AUTHORIZATION: "[REDACTED]"; context=kept'],
    'bearer token' => ['Bearer do-not-store', '[REDACTED]'],
]);

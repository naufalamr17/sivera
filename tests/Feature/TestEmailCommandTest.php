<?php

use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config()->set('mail.default', 'smtp');
});

it('prompts for the recipient and submits one plain test email', function () {
    Mail::shouldReceive('raw')->once()->andReturnUsing(function (string $text, Closure $callback): SentMessage {
        $mime = new Email;
        $callback(new Message($mime));
        expect($mime->getTo())->toHaveCount(1)
            ->and($mime->getTo()[0]->getAddress())->toBe('recipient@example.test')
            ->and($mime->getSubject())->toBe('[SIVERA] Email delivery test')
            ->and($text)->toContain('SIVERA email configuration test');
        $mime->from('sender@example.test')->text($text);

        return new SentMessage(new SymfonySentMessage($mime, Envelope::create($mime)));
    });

    $this->artisan('sivera:test-email')
        ->expectsQuestion('Recipient email address', '  recipient@example.test  ')
        ->expectsOutput('Test email submitted to recipient@example.test. Check your inbox and spam folder.')
        ->assertSuccessful();
});

it('rejects invalid recipients before attempting delivery', function (string $email) {
    Mail::shouldReceive('raw')->never();
    $this->artisan('sivera:test-email')->expectsQuestion('Recipient email address', $email)->assertFailed();
})->with(['', 'not-an-email', 'first@example.test,second@example.test', str_repeat('a', 256).'@example.test']);

it('requires interactive entry instead of silently choosing a recipient', function () {
    Mail::shouldReceive('raw')->never();
    $this->artisan('sivera:test-email', ['--no-interaction' => true])
        ->expectsOutput('Run this command interactively to enter a recipient email address.')->assertFailed();
});

it('rejects non delivering mail transports', function (string $mailer) {
    config()->set('mail.default', $mailer);
    Mail::shouldReceive('raw')->never();
    $this->artisan('sivera:test-email')->expectsQuestion('Recipient email address', 'recipient@example.test')
        ->expectsOutput('The configured mailer does not deliver external email. Configure MAIL_MAILER=smtp or another delivery transport.')
        ->assertFailed();
})->with(['log', 'array']);

it('reports transport failures without printing sensitive provider details', function () {
    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('private SMTP credentials'));
    $this->artisan('sivera:test-email')->expectsQuestion('Recipient email address', 'recipient@example.test')
        ->expectsOutput('Test email failed. Verify MAIL_* settings and mail server connectivity.')
        ->doesntExpectOutputToContain('private SMTP credentials')->assertFailed();
});

it('does not report success when the mailer suppresses a message', function () {
    Mail::shouldReceive('raw')->once()->andReturnNull();
    $this->artisan('sivera:test-email')->expectsQuestion('Recipient email address', 'recipient@example.test')
        ->expectsOutput('The mailer did not submit the test email.')->assertFailed();
});

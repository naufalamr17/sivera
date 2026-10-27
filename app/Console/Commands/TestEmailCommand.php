<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

#[Signature('sivera:test-email')]
#[Description('Ask for a recipient and send a one-off email configuration test')]
class TestEmailCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter a recipient email address.');

            return self::FAILURE;
        }

        $email = trim((string) $this->ask('Recipient email address'));
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if (in_array($transport, ['log', 'array'], true)) {
            $this->error('The configured mailer does not deliver external email. Configure MAIL_MAILER=smtp or another delivery transport.');

            return self::FAILURE;
        }

        try {
            $sent = Mail::raw(
                "This is a SIVERA email configuration test.\n\nIf you received this message, email delivery to this address is working.",
                function (Message $message) use ($email): void {
                    $message->to($email)->subject('[SIVERA] Email delivery test');
                },
            );
            if ($sent === null) {
                $this->error('The mailer did not submit the test email.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('Test email failed. Verify MAIL_* settings and mail server connectivity.');

            return self::FAILURE;
        }

        $this->info('Test email submitted to '.$email.'. Check your inbox and spam folder.');

        return self::SUCCESS;
    }
}

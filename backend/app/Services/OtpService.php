<?php

namespace App\Services;

use App\Models\EmailOtp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

class OtpService
{
    public const PURPOSE_VERIFY_EMAIL = 'email_verification';
    public const PURPOSE_RESET_PASSWORD = 'password_reset';
    public const EXPIRES_MINUTES = 10;

    public function generate(string $email, string $purpose, bool $forceNew = false): EmailOtp
    {
        $existing = EmailOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $forceNew && $existing && $existing->isValid()) {
            return $existing;
        }

        EmailOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        $otp = EmailOtp::create([
            'email' => $email,
            'otp' => $code,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
        ]);

        $this->send($email, $purpose, $code);

        return $otp;
    }

    public function verify(string $email, string $code, string $purpose): ?EmailOtp
    {
        $otp = EmailOtp::where('email', $email)
            ->where('otp', $code)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if (! $otp || ! $otp->isValid()) {
            return null;
        }

        $otp->update(['used_at' => now()]);

        return $otp;
    }

    private function send(string $email, string $purpose, string $code): void
    {
        $subject = $purpose === self::PURPOSE_RESET_PASSWORD
            ? 'KidZoo - Password Reset Code'
            : 'KidZoo - Email Verification Code';

        $body = "Your KidZoo verification code is: {$code}\n"
            . 'This code expires in ' . self::EXPIRES_MINUTES . ' minutes.';

        try {
            if ($this->sendViaMailtrap($email, $subject, $body)) {
                Log::info('OTP email sent via Mailtrap', [
                    'email' => $email,
                    'purpose' => $purpose,
                ]);
            } else {
                Mail::raw($body, function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                });

                Log::info('OTP email sent via Laravel mailer', [
                    'email' => $email,
                    'purpose' => $purpose,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('OTP email send failed, logging OTP instead', [
                'email' => $email,
                'otp' => $code,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('OTP issued', ['email' => $email, 'purpose' => $purpose, 'otp' => $code]);
    }

    private function sendViaMailtrap(string $email, string $subject, string $body): bool
    {
        $apiToken = env('MAILTRAP_API_TOKEN');

        if (! $apiToken) {
            return false;
        }

        $message = (new MailtrapEmail())
            ->from(new Address(
                env('MAILTRAP_FROM_ADDRESS', 'hello@demomailtrap.co'),
                env('MAILTRAP_FROM_NAME', config('app.name', 'KidZoo'))
            ))
            ->to(new Address($email))
            ->subject($subject)
            ->category('OTP')
            ->text($body);

        MailtrapClient::initSendingEmails(apiKey: $apiToken)->send($message);

        return true;
    }
}

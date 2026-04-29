<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('send-mail', function () {
    $apiToken = env('MAILTRAP_API_TOKEN');

    if (! $apiToken) {
        $this->error('MAILTRAP_API_TOKEN is missing in .env');
        return self::FAILURE;
    }

    $email = (new MailtrapEmail())
        ->from(new Address(
            env('MAILTRAP_FROM_ADDRESS', 'hello@demomailtrap.co'),
            env('MAILTRAP_FROM_NAME', 'Mailtrap Test')
        ))
        ->to(new Address(env('MAILTRAP_TO_ADDRESS', 'aeprahim1028@gmail.com')))
        ->subject('You are awesome!')
        ->category('Integration Test')
        ->text('Congrats for sending test email with Mailtrap!');

    $response = MailtrapClient::initSendingEmails(apiKey: $apiToken)->send($email);

    $this->info('Mailtrap send request completed.');
    $this->line(json_encode(ResponseHelper::toArray($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
})->purpose('Send a test email with Mailtrap');

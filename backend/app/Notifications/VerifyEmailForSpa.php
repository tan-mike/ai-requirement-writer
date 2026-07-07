<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailForSpa extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $frontendUrl = Config::get('app.frontend_url', 'http://localhost:3000');

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Extract query params from the signed backend URL
        $parsed = parse_url($verifyUrl);
        $query = [];
        parse_str($parsed['query'] ?? '', $query);

        // Build frontend verification URL
        return $frontendUrl . '/verify-email?' . http_build_query([
            'id' => $query['id'],
            'hash' => $query['hash'],
            'expires' => $query['expires'],
            'signature' => $query['signature'],
        ]);
    }
}

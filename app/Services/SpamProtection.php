<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Keeps bots out of the public signup forms with three invisible layers: a
 * honeypot field, a minimum fill-in time and (when configured) Cloudflare
 * Turnstile. Real visitors never notice any of them.
 */
class SpamProtection
{
    public const string HONEYPOT_FIELD = 'website';

    public const string STARTED_AT_FIELD = 'form_started_at';

    public const string TURNSTILE_FIELD = 'cf-turnstile-response';

    public const string ERROR_KEY = 'form';

    public const int MINIMUM_SECONDS = 3;

    protected const string TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Encrypted timestamp for the form, so the fill-in time cannot be forged.
     */
    public function startedAtToken(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    public function turnstileEnabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * @throws ValidationException
     */
    public function ensureHuman(?string $honeypot, ?string $startedAtToken, ?string $turnstileToken, ?string $ip): void
    {
        if (filled($honeypot) || ! $this->filledInSlowlyEnough($startedAtToken)) {
            $this->fail(__('Something went wrong. Please try again.'));
        }

        if ($this->turnstileEnabled() && ! $this->turnstilePasses($turnstileToken, $ip)) {
            $this->fail(__('We could not verify that you are human. Please try again.'));
        }
    }

    protected function filledInSlowlyEnough(?string $startedAtToken): bool
    {
        if (blank($startedAtToken)) {
            return false;
        }

        try {
            $startedAt = (int) Crypt::decryptString($startedAtToken);
        } catch (DecryptException) {
            return false;
        }

        return now()->timestamp - $startedAt >= self::MINIMUM_SECONDS;
    }

    protected function turnstilePasses(?string $token, ?string $ip): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::TURNSTILE_VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (Throwable $exception) {
            // Never lock real people out because Cloudflare is unreachable; the
            // honeypot and timing checks above still apply.
            report($exception);

            return true;
        }

        return $response->successful() && $response->json('success') === true;
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $message): never
    {
        throw ValidationException::withMessages([self::ERROR_KEY => $message]);
    }
}

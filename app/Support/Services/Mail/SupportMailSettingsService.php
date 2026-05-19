<?php

namespace App\Support\Services\Mail;

use App\Models\SupportMailSetting;

class SupportMailSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function currentPublic(): array
    {
        $setting = SupportMailSetting::query()->first();

        if ($setting === null) {
            return $this->publicFromEnv();
        }

        return [
            'mailer' => $setting->mailer,
            'host' => $setting->host,
            'port' => $setting->port,
            'encryption' => $setting->encryption,
            'username' => $setting->username,
            'hasPassword' => is_string($setting->password) && $setting->password !== '',
            'fromAddress' => $setting->from_address,
            'fromName' => $setting->from_name,
            'replyToAddress' => $setting->reply_to_address,
            'timeout' => $setting->timeout,
            'isActive' => $setting->is_active,
            'source' => 'database',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(array $payload): array
    {
        $setting = SupportMailSetting::query()->firstOrNew(['id' => 1]);

        $data = [
            'mailer' => $payload['mailer'],
            'host' => $payload['host'] ?? null,
            'port' => $payload['port'] ?? null,
            'encryption' => $payload['encryption'] ?? null,
            'username' => $payload['username'] ?? null,
            'from_address' => $payload['fromAddress'],
            'from_name' => $payload['fromName'],
            'reply_to_address' => $payload['replyToAddress'] ?? null,
            'timeout' => $payload['timeout'] ?? null,
            'is_active' => (bool) ($payload['isActive'] ?? true),
        ];

        if (array_key_exists('password', $payload) && is_string($payload['password']) && $payload['password'] !== '') {
            $data['password'] = $payload['password'];
        }

        $setting->fill($data)->save();

        return $this->currentPublic();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function effectiveSensitive(array $overrides = []): array
    {
        $setting = SupportMailSetting::query()->first();

        $base = $setting === null
            ? $this->sensitiveFromEnv()
            : [
                'mailer' => $setting->mailer,
                'host' => $setting->host,
                'port' => $setting->port,
                'encryption' => $setting->encryption,
                'username' => $setting->username,
                'password' => $setting->password,
                'fromAddress' => $setting->from_address,
                'fromName' => $setting->from_name,
                'replyToAddress' => $setting->reply_to_address,
                'timeout' => $setting->timeout,
                'isActive' => $setting->is_active,
            ];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function runtimeMailerConfig(array $config): array
    {
        $mailer = (string) ($config['mailer'] ?? 'smtp');

        if ($mailer !== 'smtp') {
            return ['transport' => $mailer];
        }

        return [
            'transport' => 'smtp',
            'host' => $config['host'] ?? config('mail.mailers.smtp.host'),
            'port' => $config['port'] ?? config('mail.mailers.smtp.port'),
            'username' => $config['username'] ?? config('mail.mailers.smtp.username'),
            'password' => $config['password'] ?? config('mail.mailers.smtp.password'),
            'scheme' => $config['encryption'] ?? config('mail.mailers.smtp.scheme'),
            'timeout' => $config['timeout'] ?? config('mail.mailers.smtp.timeout'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicFromEnv(): array
    {
        return [
            'mailer' => (string) config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'encryption' => config('mail.mailers.smtp.scheme'),
            'username' => config('mail.mailers.smtp.username'),
            'hasPassword' => is_string(config('mail.mailers.smtp.password')) && config('mail.mailers.smtp.password') !== '',
            'fromAddress' => config('mail.from.address'),
            'fromName' => config('mail.from.name'),
            'replyToAddress' => null,
            'timeout' => config('mail.mailers.smtp.timeout'),
            'isActive' => true,
            'source' => 'env',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sensitiveFromEnv(): array
    {
        return [
            'mailer' => (string) config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'encryption' => config('mail.mailers.smtp.scheme'),
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'fromAddress' => config('mail.from.address'),
            'fromName' => config('mail.from.name'),
            'replyToAddress' => null,
            'timeout' => config('mail.mailers.smtp.timeout'),
            'isActive' => true,
        ];
    }
}

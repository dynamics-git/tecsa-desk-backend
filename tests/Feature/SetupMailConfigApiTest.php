<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SetupMailConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_config_requires_auth(): void
    {
        $this->getJson('/api/setup/mail-config')
            ->assertUnauthorized();
    }

    public function test_mail_config_can_be_saved_and_read_without_password_leak(): void
    {
        $headers = $this->authHeaders();

        $this->withHeaders($headers)
            ->putJson('/api/setup/mail-config', [
                'mailer' => 'smtp',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'smtp-user',
                'password' => 'smtp-secret',
                'fromAddress' => 'support@example.com',
                'fromName' => 'Support Desk',
                'replyToAddress' => 'noreply@example.com',
                'timeout' => 30,
                'isActive' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('config.hasPassword', true)
            ->assertJsonMissingPath('config.password');

        $storedRaw = DB::table('support_mail_settings')->value('password');
        $this->assertIsString($storedRaw);
        $this->assertNotSame('smtp-secret', $storedRaw);

        $this->withHeaders($headers)
            ->getJson('/api/setup/mail-config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('config.source', 'database')
            ->assertJsonPath('config.hasPassword', true)
            ->assertJsonMissingPath('config.password');
    }

    public function test_mail_config_test_connection_accepts_array_mailer(): void
    {
        $headers = $this->authHeaders();

        $this->withHeaders($headers)
            ->postJson('/api/setup/mail-config/test-connection', [
                'to' => 'qa@example.com',
                'mailer' => 'array',
                'fromAddress' => 'support@example.com',
                'fromName' => 'Support Desk',
                'subject' => 'Mail test',
                'body' => 'hello',
                'isActive' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        User::query()->create([
            'name' => 'Setup Admin',
            'email' => 'setup-admin@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'setup-admin@example.com',
            'password' => 'password',
            'deviceName' => 'phpunit',
        ])->json('token');

        return ['Authorization' => 'Bearer '.$token];
    }
}

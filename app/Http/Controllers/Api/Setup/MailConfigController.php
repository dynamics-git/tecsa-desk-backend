<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\TestMailConfigRequest;
use App\Http\Requests\Setup\UpdateMailConfigRequest;
use App\Support\Http\ApiErrorResponse;
use App\Support\Services\Mail\SupportMailSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailConfigController extends Controller
{
    public function __construct(
        private readonly SupportMailSettingsService $mailSettings,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config' => $this->mailSettings->currentPublic(),
        ]);
    }

    public function update(UpdateMailConfigRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config' => $this->mailSettings->update($request->validated()),
        ]);
    }

    public function testConnection(TestMailConfigRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $effective = $this->mailSettings->effectiveSensitive($payload);

        if (! (bool) ($effective['isActive'] ?? true)) {
            return ApiErrorResponse::make($request, 422, 'MAIL_CONFIG_INACTIVE', 'Mail config is inactive.');
        }

        try {
            Config::set('mail.mailers.support_runtime_test', $this->mailSettings->runtimeMailerConfig($effective));

            Mail::mailer('support_runtime_test')->raw(
                (string) ($payload['body'] ?? 'This is a test email from support mail setup.'),
                function ($mail) use ($payload, $effective): void {
                    $mail->to((string) $payload['to']);
                    $mail->subject((string) ($payload['subject'] ?? 'Support Mail Setup Test'));

                    if (is_string($effective['fromAddress'] ?? null) && ($effective['fromAddress'] ?? '') !== '') {
                        $mail->from((string) $effective['fromAddress'], (string) ($effective['fromName'] ?? 'Support'));
                    }

                    if (is_string($effective['replyToAddress'] ?? null) && ($effective['replyToAddress'] ?? '') !== '') {
                        $mail->replyTo((string) $effective['replyToAddress']);
                    }
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully.',
            ]);
        } catch (Throwable $exception) {
            return ApiErrorResponse::make($request, 422, 'MAIL_TEST_FAILED', 'Unable to send test email.', [
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}

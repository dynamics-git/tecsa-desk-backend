<?php

namespace App\Support\Services\Conversation;

use App\Models\SupportTicketEmailMessage;
use App\Support\Services\Mail\SupportMailSettingsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SmtpSupportConversationEmailProvider implements SupportConversationEmailProviderInterface
{
    public function __construct(
        private readonly SupportMailSettingsService $mailSettings,
    ) {}

    /**
     * @param  array<int, array{id: string, fileName: string, disk: string, path: string}>  $attachments
     * @return array{providerMessageId: string, deliveryStatus: string, deliveredAt: string|null}
     */
    public function send(SupportTicketEmailMessage $message, array $attachments): array
    {
        $effective = $this->mailSettings->effectiveSensitive();
        $mailerName = (string) config('mail.default', 'smtp');

        if ((bool) ($effective['isActive'] ?? true)) {
            $mailerName = 'support_runtime';
            Config::set('mail.mailers.'.$mailerName, $this->mailSettings->runtimeMailerConfig($effective));
        }

        Mail::mailer($mailerName)->raw($this->bodyForMessage($message), function ($mail) use ($message, $attachments, $effective): void {
            $mail->to($message->to_recipients ?? []);

            if (is_string($effective['fromAddress'] ?? null) && ($effective['fromAddress'] ?? '') !== '') {
                $mail->from((string) $effective['fromAddress'], (string) ($effective['fromName'] ?? 'Support'));
            }

            if (is_string($effective['replyToAddress'] ?? null) && ($effective['replyToAddress'] ?? '') !== '') {
                $mail->replyTo((string) $effective['replyToAddress']);
            }

            if (($message->cc_recipients ?? []) !== []) {
                $mail->cc($message->cc_recipients);
            }

            if (($message->bcc_recipients ?? []) !== []) {
                $mail->bcc($message->bcc_recipients);
            }

            $mail->subject($message->subject);

            foreach ($attachments as $attachment) {
                $disk = $attachment['disk'];
                $path = $attachment['path'];

                if ($path !== '' && Storage::disk($disk)->exists($path)) {
                    $mail->attach(Storage::disk($disk)->path($path), ['as' => $attachment['fileName']]);
                }
            }
        });

        return [
            'providerMessageId' => 'smtp-'.Str::uuid()->toString(),
            'deliveryStatus' => 'sent',
            'deliveredAt' => now('UTC')->toIso8601ZuluString(),
        ];
    }

    private function bodyForMessage(SupportTicketEmailMessage $message): string
    {
        if (is_string($message->text_body) && trim($message->text_body) !== '') {
            return $message->text_body;
        }

        if (is_string($message->html_body) && trim($message->html_body) !== '') {
            return trim(strip_tags($message->html_body));
        }

        return '';
    }
}

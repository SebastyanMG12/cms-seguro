<?php

namespace App\Console\Commands;

use App\Models\ReceivedEmail;
use App\Services\Mail\MailSource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReceiveEmails extends Command
{
    protected $signature = 'mail:receive {--limit=10}';

    protected $description = 'Consulta la fuente de correo y guarda mensajes nuevos';

    public function handle(MailSource $source): int
    {
        $limit = max(1, min(50, (int) $this->option('limit')));
        $new = 0;
        $skipped = 0;

        foreach ($source->fetch($limit) as $item) {
            $messageId = Str::limit((string) ($item['message_id'] ?? ''), 255, '');
            $fromEmail = Str::limit((string) ($item['from_email'] ?? ''), 255, '');

            if ($messageId === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            $email = ReceivedEmail::firstOrCreate(
                ['message_id' => $messageId],
                [
                    'from_email' => $fromEmail,
                    'from_name' => Str::limit((string) ($item['from_name'] ?? ''), 255, ''),
                    'subject' => Str::limit((string) ($item['subject'] ?? ''), 255, ''),
                    'body' => Str::limit((string) ($item['body'] ?? ''), 20000, ''),
                    'received_at' => $item['received_at'] ?? now(),
                ]
            );

            $email->wasRecentlyCreated ? $new++ : $skipped++;
        }

        $this->info("Nuevos: {$new} | Omitidos/duplicados: {$skipped}");

        return self::SUCCESS;
    }
}

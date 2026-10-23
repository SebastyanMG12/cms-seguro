<?php

namespace App\Services\Mail;

use Webklex\IMAP\Facades\Client;

class ImapMailSource implements MailSource
{
    public function fetch(int $limit = 10): array
    {
        $client = Client::account('default');
        $client->connect();

        try {
            $folder = $client->getFolder('INBOX');

            $messages = $folder->messages()
                ->unseen()
                ->leaveUnread()
                ->limit($limit)
                ->get();

            $items = [];

            foreach ($messages as $message) {
                $from = $message->getFrom()->first();

                // Solo texto: si no hay versión de texto plano, se quitan las etiquetas HTML.
                $body = (string) $message->getTextBody();
                if ($body === '') {
                    $body = strip_tags((string) $message->getHTMLBody());
                }

                $items[] = [
                    'message_id' => (string) $message->getMessageId(),
                    'from_email' => (string) ($from->mail ?? ''),
                    'from_name' => (string) ($from->personal ?? ''),
                    'subject' => (string) $message->getSubject(),
                    'body' => $body,
                    'received_at' => $message->getDate()->first()?->format('Y-m-d H:i:s'),
                ];

                // Se marca como visto para no volver a leerlo. Los adjuntos nunca se descargan.
                $message->setFlag('Seen');
            }

            return $items;
        } finally {
            $client->disconnect();
        }
    }
}
<?php

namespace App\Services\Mail;

class FakeMailSource implements MailSource
{
    public function fetch(int $limit = 10): array
    {
        $path = storage_path('app/private/mail-fixtures/inbox.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? array_slice($data, 0, $limit) : [];
    }
}
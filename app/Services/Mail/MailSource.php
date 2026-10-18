<?php

namespace App\Services\Mail;

interface MailSource
{
    /**
     * @return array<int, array{message_id:string, from_email:string, from_name:?string, subject:?string, body:?string, received_at:?string}>
     */
    public function fetch(int $limit = 10): array;
}
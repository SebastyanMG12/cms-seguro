<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivedEmail extends Model
{
    protected $fillable = [
        'message_id', 'from_email', 'from_name', 'subject',
        'body', 'received_at', 'is_read',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailInteraction extends Model
{
    protected $fillable = [
        'message_id',
        'email_address',
        'role',
        'subject',
        'sent_at',
        'label_name'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

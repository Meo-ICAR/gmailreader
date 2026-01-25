<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmailLabel extends Model
{
    //
    protected $fillable = ['google_id', 'name', 'type', 'dominio'];
}

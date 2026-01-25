<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referente extends Model
{
    //  protected $table = 'referenti';  // Specifichiamo la tabella per sicurezza
    protected $fillable = ['name', 'email', 'label_id'];
}

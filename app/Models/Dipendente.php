<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dipendente extends Model
{
    // <--- AGGIUNGI QUESTA RIGA FONDAMENTALE
    //  protected $table = 'dipendenti';
    protected $fillable = ['name', 'email'];
}

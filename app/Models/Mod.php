<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mod extends Model
{
    protected $table = 'mods';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

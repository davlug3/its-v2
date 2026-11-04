<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $table = 'states';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

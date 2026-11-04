<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachement extends Model
{
    protected $table = 'attachements';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

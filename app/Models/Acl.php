<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acl extends Model
{
    protected $table = 'acls';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

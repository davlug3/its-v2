<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthAudit extends Model
{
    protected $table = 'auth_audit';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

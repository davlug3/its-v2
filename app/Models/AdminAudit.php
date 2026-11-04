<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAudit extends Model
{
    protected $table = 'admin_audit';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketLog extends Model
{
    protected $table = 'tickets_log';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

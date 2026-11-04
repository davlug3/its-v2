<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    protected $table = 'application_settings';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

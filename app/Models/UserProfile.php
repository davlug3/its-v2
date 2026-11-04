<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserProfile extends Model
{
    use SoftDeletes;

    protected $table = 'user_profile';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];
}

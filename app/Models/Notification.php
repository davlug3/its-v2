<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $subject
 * @property string $data
 * @property int $dt
 * @property int $state
 */
class Notification extends Model
{
    protected $table = 'notifications';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

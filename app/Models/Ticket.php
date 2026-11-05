<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject
 * @property string $issue
 * @property string $preview
 * @property string|null $resolution
 * @property int $created_uid
 * @property int $created_date
 * @property int $created_time
 * @property int $category_id
 * @property int $location_id
 * @property string|null $place
 * @property string|null $contact
 * @property int $assigned_gid
 * @property int $assigned_uid
 * @property int $assigned_date
 * @property int $assigned_time
 * @property int $state_id
 * @property int $resolved_uid
 * @property int $resolved_date
 * @property int $resolved_time
 * @property int $eu_uid
 * @property int $approved_by_supervisor
 * @property string $source
 * @property string|null $email_message_id
 * @property int $units_count
 * @property Carbon|null $updated_at
 */
class Ticket extends Model
{
    protected $table = 'tickets';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

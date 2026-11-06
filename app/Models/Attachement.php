<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $file_name_org
 * @property int $file_name
 * @property int $ticket_id
 * @property string $storage_path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int $uploaded_by
 * @property Carbon|null $created_at
 */
class Attachement extends Model
{
    protected $table = 'attachements';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

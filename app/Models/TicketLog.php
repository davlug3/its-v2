<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $data
 * @property int $date
 * @property int $time
 * @property string|null $action
 * @property array<string, mixed>|null $meta
 */
class TicketLog extends Model
{
    protected $table = 'tickets_log';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}

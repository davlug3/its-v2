<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the legacy `its.users` table.
 *
 * Identity, location, and state come from `jblmrhpayroll` at runtime.
 * See DECISIONS.md C6 and C8.
 */
class User extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    public function save(array $options = []): bool
    {
        throw new \RuntimeException('its.users is read-only. Use user_profile for helpdesk attributes.');
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('its.users is read-only.');
    }
}

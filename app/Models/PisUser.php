<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $name
 * @property string $mname
 * @property string $lname
 * @property int $access
 * @property string $status
 * @property int $active
 * @property int $type
 */
class PisUser extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $connection = 'payroll';

    protected $table = 'user';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $hidden = ['password', 'pispassword', 'jblpword'];

    protected $guarded = [];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        // Prefer the modern hash if it exists.
        $profile = UserProfile::where('user_id', $this->id)->first();
        if ($profile !== null && ! empty($profile->password_v2)) {
            return $profile->password_v2;
        }

        return (string) $this->password;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}

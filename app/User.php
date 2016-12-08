<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    const TABLE_NAME="users";
    const ID = 'id';
    const FIREBASE_ID = 'firebase_id';
    const FIRST_NAME = 'first_name';
    const LAST_NAME = 'last_name';
    const SOCIAL = 'social';
    const MIDDLE_NAME = 'middle_name';
    const AVATAR_URL = 'avatar_url';
    const PHONE = 'phone';
    const EMAIL = 'email';
    const ADDRESS = 'address';

    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table=self::TABLE_NAME;
    protected $fillable = [
        self::ID,self::SOCIAL, self::FIRST_NAME, self::LAST_NAME, self::MIDDLE_NAME, self::AVATAR_URL, self::PHONE, self::EMAIL,
        self::ADDRESS,self::FIREBASE_ID
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [

    ];
}

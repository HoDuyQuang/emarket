<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    const TABLE_NAME="device_tokens";
    const DEVICE_TOKEN = 'device_token';
    const OS = 'os';
    const USER_ID = 'user_id';
    const ID = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table=self::TABLE_NAME;
    protected $fillable = [
        self::ID, self::USER_ID, self::DEVICE_TOKEN, self::OS,
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];
}

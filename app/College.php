<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class College extends Model
{
    const TABLE_NAME="colleges";
    const ID = 'id';
    const NAME = 'name';
    const LAT = 'lat';
    const LONG = 'long';
    const DISTRICT_ID = 'district_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table=self::TABLE_NAME;
    protected $fillable = [
        self::ID, self::NAME, self::LAT, self::LONG, self::DISTRICT_ID
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];
}

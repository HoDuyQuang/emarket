<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    const TABLE_NAME="districts";
    const ID = 'id';
    const NAME = 'name';
    const LAT = 'lat';
    const LONG = 'long';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table=self::TABLE_NAME;
    protected $fillable = [
        self::ID, self::NAME, self::LAT, self::LONG
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];
}

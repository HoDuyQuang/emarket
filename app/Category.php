<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    const TABLE_NAME="categories";
    const ID = 'id';
    const NAME = 'name';
    const ICON = 'icon';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table=self::TABLE_NAME;
    protected $fillable = [
        self::ID, self::NAME, self::ICON
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];
}

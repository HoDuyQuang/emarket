<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    const TABLE_NAME="posts";
    const ID = 'id';
    const USER_ID = 'user_id';
    const TITLE = 'title';
    const CONTENT = 'content';
    const COLLEGE_ID = 'college_id';
    const CATEGORY_ID = 'category_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table=self::TABLE_NAME;
    protected $fillable = [
        self::ID, self::USER_ID, self::TITLE, self::CONTENT, self::COLLEGE_ID, self::CATEGORY_ID
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];
}

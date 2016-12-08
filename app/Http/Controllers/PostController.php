<?php

namespace App\Http\Controllers;

use App\Category;
use App\College;
use App\DeviceToken;
use App\Follow;
use App\ListHasSong;
use App\ListSong;
use App\Post;
use App\RememberToken;
use App\Social;
use App\Song;
use App\User;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Facebook;
use Google_Client;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Hash;
use App\District;
use Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use JWTAuth;
use Validator;
use Illuminate\Support\Facades\DB;

use Image;

class PostController extends Controller
{

    public function get_home(Request $request)
    {
        $articles = DB::table(Post::TABLE_NAME)
            ->select(
                Post::TABLE_NAME.'.'.Post::ID,
                Post::TABLE_NAME.'.'.Post::TITLE,
                DB::raw('SUBSTRING('.Post::TABLE_NAME.'.'.Post::CONTENT.', 1, 20) as '.Post::CONTENT)
//                DB::raw(Category::TABLE_NAME.'.'.Category::ICON.' as category_icon'),
//                DB::raw(Category::TABLE_NAME.'.'.Category::NAME.' as category_name')
//                't_image.'.Articles::GUID.' as featured_image'
            )
//            ->join(Post::TABLE_NAME,
//                Post::TABLE_NAME.'.'.Post::ID,
//                '=',
//                Category::TABLE_NAME.'.'.Category::ID)
//            ->join(Term::TABLE_NAME,
//                CategoryHasPost::TABLE_NAME.'.'.CategoryHasPost::TERM_ID,
//                '=',
//                Term::TABLE_NAME.'.'.Term::ID)
//            ->join(PostMeta::TABLE_NAME,
//                PostMeta::TABLE_NAME.'.'.PostMeta::POST_ID,
//                '=',
//                Articles::TABLE_NAME.'.'.Articles::ID)
//            ->join(Articles::TABLE_NAME.' as t_image',
//                PostMeta::TABLE_NAME.'.'.PostMeta::META_VALUE,
//                '=',
//                't_image.'.Articles::ID)
//            ->where(Articles::TABLE_NAME.'.'.Articles::POST_STATUS,'=','publish')
//            ->where(Articles::TABLE_NAME.'.'.Articles::POST_TYPE,'=','post')
//            ->where(PostMeta::TABLE_NAME.'.'.PostMeta::META_key,'=','_thumbnail_id')
//            ->where(function ($query) {
//                $query->where(CategoryHasPost::TABLE_NAME.'.'.CategoryHasPost::TERM_ID, '=', 56)
//                    ->orWhere(CategoryHasPost::TABLE_NAME.'.'.CategoryHasPost::TERM_ID, '=', 57)
//                    ->orWhere(CategoryHasPost::TABLE_NAME.'.'.CategoryHasPost::TERM_ID, '=', 58)
//                    ->orWhere(CategoryHasPost::TABLE_NAME.'.'.CategoryHasPost::TERM_ID, '=', 83)
//                    ->orWhere(CategoryHasPost::TABLE_NAME.'.'.CategoryHasPost::TERM_ID, '=', 272)
//                ;
//            })
        ;
        if ($request->last_article_id) {
            $articles = $articles->where(
                Post::ID, '<', $request->last_article_id
            );
        }
        if ($request->limit) {
            $articles = $articles->limit($request->limit);
        } else {
            $articles = $articles->limit(10);
        }
        $articles = $articles->orderBy(Post::TABLE_NAME.'.'.Post::CREATED_AT, 'desc')->get();
        return $this->getReturnMessage(self::SUCCESS,
            [
                'posts' => $articles,
            ], null);
    }

    public function sync(Request $request)
    {
        $user = $this->getUserFromToken();
        if ($user instanceof User) {
            $data = $request->all();
            if (isset($data['email'])) {
                $validator = Validator::make($request->all(), [
                    'email' => 'email',
                ]);
                if ($validator->fails()) {
                    return self::getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
                }
            }
            $validator = Validator::make($data, [
                'lists' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
            }
            $check = $this->sync_list($data, $user);
            if (!is_bool($check)) {
                return $check;
            }
            if (isset($data['new_lists'])) {
                $this->create_list($data, $user);
            }
            $user = UserController::update_user($request, $user);

            $lists = ListSong::where([
                ListSong::USER_ID => $user->id
            ])->get();
            return $this->getReturnMessage(self::SUCCESS, [
                'user' => $user,
                'lists' => $lists,
            ], null);
        } else {
            return $user;
        }
    }

    public function sync_list($data, $user)
    {
        $new_lists = json_decode($data['lists']);
        if ($new_lists != null) {
            $lists = ListSong::where([
                ListSong::USER_ID => $user->id
            ])->get();
            foreach ($lists as $list) {
                $list_is_exist = false;
                foreach ($new_lists as $new_list) {
                    if ($list->id == $new_list->id) {
                        $list_is_exist = true;
                    }
                }
                if (!$list_is_exist) {
                    ListHasSong::where(ListHasSong::LIST_ID, '=', $list->id)
                        ->delete();
                    ListSong::where(ListSong::ID, '=', $list->id)->delete();
                }
            }
            $new_list_ids = array();
            foreach ($new_lists as $new_list) {
                $new_list_ids[] = $new_list->id;
            }
            if (ListSong::
                whereIn(ListSong::ID, $new_list_ids)
                    ->count() != count($new_list_ids)
            ) {
                return $this->getReturnMessage(self::INVALID_PARAMS, null, 'Invalid List Id');
            }
            foreach ($new_lists as $new_list) {
                $song_id_list = $new_list->song_id_list;
                if ($song_id_list) {
                    if (Song::select(Song::ID)
                            ->whereIn(Song::ID, $song_id_list)
                            ->count() != count($song_id_list)
                    ) {
                        return $this->getReturnMessage(self::INVALID_PARAMS, null, 'Invalid song id');
                    }

                } else {
                    return $this->getReturnMessage(self::INVALID_PARAMS, null, 'Invalid song list');
                }
            }
            foreach ($new_lists as $new_list) {
                $song_id_list = $new_list->song_id_list;
                ListSong::where(ListSong::ID, '=', $new_list->id)
                    ->update(
                        [
                            ListSong::TITLE => $new_list->title
                        ]
                    );
                ListHasSong::where([
                    ListHasSong::LIST_ID => $new_list->id,
                ])->delete();
                foreach ($song_id_list as $song_id) {
                    ListHasSong::create([
                        ListHasSong::LIST_ID => $new_list->id,
                        ListHasSong::SONG_ID => $song_id
                    ]);
                };
            }
            return true;
        } else {
            return $this->getReturnMessage(self::INVALID_PARAMS, null, 'Invalid lists Json format ');
        }
    }

    function create_list($data, $user)
    {
        $new_lists = json_decode($data['new_lists']);
        dd($new_lists);
        if ($new_lists) {
            foreach ($new_lists as $new_list) {
                $list = ListSong::create([
                    ListSong::USER_ID => $user->id,
                    ListSong::TITLE => $new_list->title,
                ]);


                foreach ($new_list->song_id_list as $song_id) {
                    if (!Song::where([
                        Song::ID => $song_id
                    ])->first()
                    ) {
                        $list->delete();
                        return $this->getReturnMessage(self::INVALID_PARAMS, null, 'Invalid song id');
                    }
                }

                foreach ($new_list->song_id_list as $song_id) {
                    ListHasSong::create([
                        ListHasSong::LIST_ID => $list->id,
                        ListHasSong::SONG_ID => $song_id
                    ]);
                };
            }
            return true;
        } else {
            return $this->getReturnMessage(self::INVALID_PARAMS, null, 'Invalid Json Array');
        }
    }


}
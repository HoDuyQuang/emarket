<?php

namespace App\Http\Controllers;

use App\College;
use App\DeviceToken;
use App\Follow;
use App\ListHasSong;
use App\ListSong;
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

class RequestController extends Controller
{

    public function init(Request $request)
    {
        return $this->getReturnMessage(self::SUCCESS, [
            'districts' => District::all(),
            'colleges' => College::all(),
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
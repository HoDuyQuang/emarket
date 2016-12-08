<?php

namespace App\Http\Controllers;

use App\DeviceToken;
use App\User;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Http\Requests;
use Illuminate\Support\Facades\Hash;
use Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use JWTAuth;
use Validator;
class Controller extends BaseController
{
    const URL_UPLOADS_IMAGES_AVATARS = '/uploads/images/avatars/';
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    const HARPA_SECRET_SERVER_KEY = "harpa_secret_server_key";
    const SUCCESS = [
        "code" => 100,
        "message" => "Success"
    ];
    const INVALID_PARAMS = [
        "code" => 101,
        "message" => "Invalid param"
    ];
    const SQL_ERROR = [
        "code" => 102,
        "message" => "SQL Error"
    ];
    const FIREBASE_UNIQUE = [
        "code" => 103,
        "message" => "Firebase unique"
    ];
    const LOGIN_FAIL = [
        "code" => 104,
        "message" => "Login failed"
    ];
    const INVALID_TOKEN = [
        "code" => 105,
        "message" => "Invalid token"
    ];

    /**
     * @return mixed
     */
    public function getUserFromToken()
    {
        try {
            $jwt_token = JWTAuth::getToken();
            if ($jwt_token) {
                $jwt_token = JWT::decode($jwt_token, self::HARPA_SECRET_SERVER_KEY);
                $device_token = DeviceToken::where([
                    DeviceToken::ID => $jwt_token->id,
                ])->first();
                if ($device_token) {
                    return User::findOrFail($device_token->user_id);
                } else {
                    return $this->getReturnMessage(self::INVALID_TOKEN,
                        null, null);
                }
            } else {
                return $this->getReturnMessage(self::INVALID_PARAMS,
                    null, "Authentication token not found");
            }
        } catch (JWTException $exception) {
            return $this->getReturnMessage(self::INVALID_PARAMS,
                null, "Authentication token not found");
        }
    }

    public function getReturnMessage(array $result_code, $data = null, $error = null)
    {
        $result = array(
            'code' => $result_code["code"],
            'message' => $result_code["message"],
        );
        if ($data) {
            $result['data'] = $data;
        }
        if ($error) {
            $result['error'] = $error;
        }
        json_encode($result);
        $response = Response::make($result, 200);
        $response->header('Content-Type', 'application/json');
        return $response;
    }

//    public function setUserAvatarUrl($user){
////        dd(strpos($user->avatar_url, 'http'));
//        if(substr( $user->avatar_url, 0, 4 ) !== "http") {
//            $user->avatar_url = url('/') . self::URL_UPLOADS_IMAGES_AVATARS . $user->avatar_url;
//        }
//    }

//    public function getAvatarUrl($filename){
//        return url('/'). self::URL_UPLOADS_IMAGES_AVATARS .$filename;
//    }
}

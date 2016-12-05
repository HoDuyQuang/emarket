<?php

namespace App\Http\Controllers;

use App\DeviceToken;
use App\Follow;
use App\ListSong;
use App\RememberToken;
use App\Social;
use App\User;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Facebook;
use Google_Client;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Hash;

use Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use JWTAuth;
use Validator;
use Illuminate\Support\Facades\DB;

use Image;

class UserController extends Controller
{


    const FACEBOOK = "FACEBOOK";

    const GOOGLE = "GOOGLE";

    function register(Request $request)
    {
        $data = $request->all();
//        $pass = str_replace("\r\n", "", $request->all()['password']);
        $validator = Validator::make($data, [
            User::FIREBASE_ID => 'required',
            DeviceToken::DEVICE_TOKEN => 'required',
            DeviceToken::OS => 'required'
        ]);
        if ($validator->fails()) {
            return $this->getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
        }

        $validator = Validator::make($request->all(), [
            User::FIREBASE_ID => 'unique:' . User::TABLE_NAME,
        ]);
        if ($validator->fails()) {
            return $this->getReturnMessage(self::EMAIL_UNIQUE, null, $validator->errors()->all());
        }
//        //avatar
        if ($request->file('avatar')) {
            $imageName = time() . 'test.' . $request->file('avatar')->getClientOriginalExtension();
//            $request->file('avatar')->move(base_path() . '/public/uploads/images/avatars/', $imageName);
//            Image::make($request->file('avatar')->getRealPath())->resize(200, 200)->save($path);
            // create an image manager instance with favored driver
            $manager = new ImageManager(array('driver' => 'imagick'));

            // to finally create image instances
            $manager->make($request->file('avatar')->getRealPath())->resize(300, 200)->save(base_path() . '/public/uploads/images/avatars/' . $imageName);
            $data['avatar_url'] = $imageName;
        }
        $user = User::create($data);

        $device_token = $this->authWithDeviceToken($data, $user);

        return $this->getReturnMessage(self::SUCCESS, [
            'user' => $user,
            "access_token" => $this->createJWT($device_token),
        ], null);
    }

    function social(Request $request)
    {
        $data = $request->all();
//        $pass = str_replace("\r\n", "", $request->all()['password']);
        $validator = Validator::make($data, [
//            User::USER_LOGIN => 'required',
            User::SOCIAL => 'required|exists:' . Social::TABLE_NAME . ',' . Social::SOCIAL,
            DeviceToken::DEVICE_TOKEN => 'required',
            DeviceToken::OS => 'required'
        ]);
        if ($validator->fails()) {
            return $this->getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
        }

        if (strcmp($data[User::SOCIAL], self::FACEBOOK) == 0) {
            $validator = Validator::make($data, [
                'facebook_token' => 'required'
            ]);
            if ($validator->fails()) {
                return $this->getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
            }
            $fb = new Facebook([
                'app_id' => '1414894205191216',
                'app_secret' => '23dfd177acbab9b6956eaf71e909ecb7',
                'default_graph_version' => 'v2.7',
            ]);
            try {
                $res = $fb->get('/me?fields=id,name,email,picture,first_name,last_name,middle_name,bio', $data['facebook_token']);
                $userNode = $res->getGraphUser();
            } catch (FacebookResponseException $exception) {
                return $this->getReturnMessage(self::INVALID_PARAMS, null, "Invalid OAuth Facebook access token");
            }
//            dd($userNode);
            $user = User::select(User::TABLE_NAME . '.*')
                ->where([
                    User::USER_LOGIN => $userNode->getId()
                ])
                ->first();

            if (!$user) {
                $imageName = time() . '.jpg';
                file_put_contents(base_path() . '/public/uploads/images/avatars/' . $imageName, file_get_contents($userNode->getPicture()->getUrl()));
                User::create([
                    User::USER_LOGIN => $userNode->getId(),
                    User::FIRST_NAME => $userNode->getFirstName(),
                    User::LAST_NAME => $userNode->getLastName(),
                    User::MIDDLE_NAME => $userNode->getMiddleName(),
                    User::EMAIL => $userNode->getEmail(),
                    User::AVATAR_URL => $imageName,
                    User::BIO => $userNode->getField('bio'),
                    User::SOCIAL => self::FACEBOOK,
                ]);
                $user = User::select(User::TABLE_NAME . '.*')
                    ->where([
                        User::USER_LOGIN => $userNode->getId()
                    ])
                    ->first();
            }
            $device_token = $this->authWithDeviceToken($data, $user);

            return $this->getReturnMessage(self::SUCCESS, [
                'user' => $user,
                "access_token" => $this->createJWT($device_token),
            ], null);
        }
        if (strcmp($data[User::SOCIAL], self::GOOGLE) == 0) {
            $client = new Google_Client();
//            $client->setAuthConfig('client_secrets.json');
            $client->setClientId("456924203419-g5fsc2q3340d50bvrjltlga4cv47r6ju.apps.googleusercontent.com");
            $client->setClientSecret("UJeeqBYS5lc0UBK_mAyxxq4c");
//            $client->addScope([
//                'https://www.googleapis.com/auth/userinfo.profile',
//                'https://www.googleapis.com/auth/userinfo.email',
//            ]);
//            $client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/oauth2callback.php');
//            dd($client->authenticate($data['google_id_token']));
            $client->setDeveloperKey("AIzaSyAnh_wIHhSJjdIY5mG7y-Ryh09pvC-6AWo");
//            $client->get
//            dd($client->getAuth());
            $ticket = $client->verifyIdToken($data['google_id_token']);
            $attrs = $ticket->getAttributes();

            $user = User::select(User::TABLE_NAME . '.*')
                ->where([
                    User::USER_LOGIN => $attrs['payload']['sub']
                ])
                ->first();
            if (!$user) {
                $imageName = time() . '.jpg';
                file_put_contents(base_path() . '/public/uploads/images/avatars/' . $imageName, file_get_contents($attrs['payload']['picture']));

                User::create([
                    User::USER_LOGIN => $attrs['payload']['sub'],
                    User::FIRST_NAME => $attrs['payload']['given_name'],
                    User::LAST_NAME => $attrs['payload']['family_name'],
                    User::EMAIL => $attrs['payload']['email'],
//                    User::AVATAR_URL => $attrs['payload']['picture'],
                    User::AVATAR_URL => $imageName,
                    User::SOCIAL => self::GOOGLE,
                ]);
                $user = User::select(User::TABLE_NAME . '.*')
                    ->where([
                        User::USER_LOGIN => $attrs['payload']['sub']
                    ])
                    ->first();
            }
            $device_token = $this->authWithDeviceToken($data, $user);

            return $this->getReturnMessage(self::SUCCESS, [
                'user' => $user,
                "access_token" => $this->createJWT($device_token),
            ], null);

        }
        $user_login = $data[User::USER_LOGIN];
        $validator = Validator::make($request->all(), [
            User::USER_LOGIN => 'unique:' . User::TABLE_NAME,
        ]);
        if ($validator->fails()) {
            $user = User::select(User::TABLE_NAME . '.*')
                ->where([
                    User::USER_LOGIN => $user_login
                ])
                ->first();
        } else {
            if ($request->file('avatar')) {
                $imageName = time() . '.' . $request->file('avatar')->getClientOriginalExtension();
                $request->file('avatar')->move(base_path() . '/public/uploads/images/avatars/', $imageName);
                $data['avatar_url'] = $imageName;
            }
            $user = User::create($data);
        }

        $device_token = $this->authWithDeviceToken($data, $user);

        return $this->getReturnMessage(self::SUCCESS, [
            'user' => $user,
            "access_token" => $this->createJWT($device_token),
        ], null);
    }

    function login(Request $request)
    {
        $data = $request->all();
//        $pass = str_replace("\r\n", "", $request->all()['password']);
        $validator = Validator::make($data, [
            User::USER_LOGIN => 'required|email',
            User::USER_PASS => 'required|min:6',
            DeviceToken::DEVICE_TOKEN => 'required',
            DeviceToken::OS => 'required'
        ]);
        if ($validator->fails()) {
            return $this->getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
        }

        $user = User::select(User::TABLE_NAME . '.*')
            ->where([
                User::USER_LOGIN => $data[User::USER_LOGIN]
            ])
            ->first();

        if (!$user) {
            return $this->getReturnMessage(self::LOGIN_FAIL, null, "Wrong username");
        }
        if (!password_verify($data[User::USER_PASS], $user->user_pass)) {
            return $this->getReturnMessage(self::LOGIN_FAIL, null, "Wrong password");
        }

        $device_token = $this->authWithDeviceToken($data, $user);
        $this->filterUser($user);

        return $this->getReturnMessage(self::SUCCESS,
            [
                'user' => $user,
                "access_token" => $this->createJWT($device_token),
            ], null);
    }


    public function get_profile(Request $request)
    {
        $user = $this->getUserFromToken();
//        $this->setUserAvatarUrl($user);
        $followings = Follow::where([
            Follow::FOLLOWER_ID => $user->id,
        ])->count();
        $followers = Follow::where([
            Follow::FOLLOWING_ID => $user->id,
        ])->count();
        $lists = ListSong::where([
            ListSong::USER_ID => $user->id
        ])->count();
        if ($user instanceof User) {
            return $this->getReturnMessage(self::SUCCESS,
                [
                    'user' => $user,
                    'followings' => $followings,
                    'followers' => $followers,
                    'lists' => $lists,
                ], null);
        } else {
            return $user;
        }
    }

    public function get_all(Request $request)
    {
        $user = $this->getUserFromToken();
        if ($user instanceof User) {
            $users = DB::table(User::TABLE_NAME)
                ->select(
                    User::TABLE_NAME . '.' . User::ID,
                    User::USER_LOGIN,
                    User::SOCIAL,
                    User::FIRST_NAME,
                    User::LAST_NAME,
                    User::MIDDLE_NAME,
                    User::AVATAR_URL,
                    DB::raw('IF(t_follow.' . Follow::ID . ' IS NOT NULL,TRUE ,FALSE) AS is_follow ')
//                DB::raw('CONCAT("'.url('/'). self::URL_UPLOADS_IMAGES_AVATARS.'",'.User::AVATAR_URL.') AS '.User::AVATAR_URL)
                )
                ->leftjoin(DB::raw('(SELECT ' . Follow::TABLE_NAME . '.' . Follow::ID . ','
                    . Follow::TABLE_NAME . '.' . Follow::FOLLOWING_ID .
                    ' FROM ' . Follow::TABLE_NAME . ') AS t_follow'),
                    User::TABLE_NAME . '.' . User::ID, '=', 't_follow' . '.' . Follow::FOLLOWING_ID);
        } else {
            $users = DB::table(User::TABLE_NAME)
                ->select(
                    User::ID,
                    User::USER_LOGIN,
                    User::SOCIAL,
                    User::FIRST_NAME,
                    User::LAST_NAME,
                    User::MIDDLE_NAME,
                    User::AVATAR_URL
//                DB::raw('CONCAT("'.url('/'). self::URL_UPLOADS_IMAGES_AVATARS.'",'.User::AVATAR_URL.') AS '.User::AVATAR_URL)
                );
        }


        if ($request->last_user_id) {
            $users = $users->where(
                User::TABLE_NAME . '.' . User::ID, '<', $request->last_user_id
            );
        }
        if ($request->limit) {
            $users = $users->limit($request->limit);
        } else {
            $users = $users->limit(3);
        }
        $users = $users->orderBy(User::TABLE_NAME . '.' . User::ID, 'desc')->get();
        return $this->getReturnMessage(self::SUCCESS,
            [
                'users' => $users,
            ], null);
    }

    public function search(Request $request)
    {
        DB::enableQueryLog();
        $users = DB::table(User::TABLE_NAME)
            ->select(
                User::ID,
                User::USER_LOGIN,
                User::SOCIAL,
                User::FIRST_NAME,
                User::LAST_NAME,
                User::MIDDLE_NAME,
                User::AVATAR_URL);
        if ($request->email) {
            $users = $users->where(
                User::EMAIL, 'like', '%' . $request->email . '%'
            );
        }
        if ($request->name) {
            $users = $users->where(
                function ($query) use ($request) {
                    $query->where(User::FIRST_NAME, 'like', '%' . $request->name . '%')
                        ->orWhere(User::MIDDLE_NAME, 'like', '%' . $request->name . '%')
                        ->orWhere(User::LAST_NAME, 'like', '%' . $request->name . '%');
                }
            );
        }

        if ($request->last_user_id) {
            $users = $users->where(
                User::ID, '<', $request->last_user_id
            );
        }
        if ($request->limit) {
            $users = $users->limit($request->limit);
        }

        $users = $users->orderBy(User::ID, 'desc')->get();
//        dd(
//            DB::getQueryLog()
//    );
        return $this->getReturnMessage(self::SUCCESS,
            [
                'users' => $users,
            ], null);
    }

    function user_detail(Request $request)
    {

        $user = User::where([
            User::ID => $request->id])->first();
        if ($user) {
            return $this->getReturnMessage(self::SUCCESS, [
                'user' => $user,
            ], null);
        } else {
            return $this->getReturnMessage(self::INVALID_PARAMS, null, "Invalid user id");
        }
    }

    public function create_list(Request $request)
    {
        return "abc";
    }

    public function update_profile(Request $request)
    {
        $user = $this->getUserFromToken();
        if ($user instanceof User) {
            if (isset($data['email'])) {
                $validator = Validator::make($request->all(), [
                    'email' => 'email',
                ]);
                if ($validator->fails()) {
                    return self::getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
                }
            }
            $user = $this->update_user($request, $user);
            return $this->getReturnMessage(self::SUCCESS, ['user' => $user], null);
        } else {
            return $user;
        }
    }

    static public function update_user($request, $user)
    {
        $data = $request->all();
        //avatar
        if ($request->file('avatar')) {
            $imageName = time() . '.' . $request->file('avatar')->getClientOriginalExtension();
            self::smart_resize_image($request->file('avatar'), null, 200, 200, false, base_path() . '/public/uploads/images/avatars/' . $imageName, false, false, 100);
            $data['avatar_url'] = $imageName;
        }
        unset($data[User::ID]);
        unset($data[User::USER_PASS]);
        unset($data[User::USER_LOGIN]);
        $user->update($data);
        return $user;
    }


    function smart_resize_image($file,
                                $string = null,
                                $width = 0,
                                $height = 0,
                                $proportional = false,
                                $output = 'file',
                                $delete_original = true,
                                $use_linux_commands = false,
                                $quality = 100,
                                $grayscale = false
    )
    {

        if ($height <= 0 && $width <= 0) return false;
        if ($file === null && $string === null) return false;
        # Setting defaults and meta
        $info = $file !== null ? getimagesize($file) : getimagesizefromstring($string);
        $image = '';
        $final_width = 0;
        $final_height = 0;
        list($width_old, $height_old) = $info;
        $cropHeight = $cropWidth = 0;
        # Calculating proportionality
        if ($proportional) {
            if ($width == 0) $factor = $height / $height_old;
            elseif ($height == 0) $factor = $width / $width_old;
            else                    $factor = min($width / $width_old, $height / $height_old);
            $final_width = round($width_old * $factor);
            $final_height = round($height_old * $factor);
        } else {
            $final_width = ($width <= 0) ? $width_old : $width;
            $final_height = ($height <= 0) ? $height_old : $height;
            $widthX = $width_old / $width;
            $heightX = $height_old / $height;

            $x = min($widthX, $heightX);
            $cropWidth = ($width_old - $width * $x) / 2;
            $cropHeight = ($height_old - $height * $x) / 2;
        }
        # Loading image to memory according to type
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $file !== null ? $image = imagecreatefromjpeg($file) : $image = imagecreatefromstring($string);
                break;
            case IMAGETYPE_GIF:
                $file !== null ? $image = imagecreatefromgif($file) : $image = imagecreatefromstring($string);
                break;
            case IMAGETYPE_PNG:
                $file !== null ? $image = imagecreatefrompng($file) : $image = imagecreatefromstring($string);
                break;
            default:
                return false;
        }

        # Making the image grayscale, if needed
        if ($grayscale) {
            imagefilter($image, IMG_FILTER_GRAYSCALE);
        }

        # This is the resizing/resampling/transparency-preserving magic
        $image_resized = imagecreatetruecolor($final_width, $final_height);
        if (($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG)) {
            $transparency = imagecolortransparent($image);
            $palletsize = imagecolorstotal($image);
            if ($transparency >= 0 && $transparency < $palletsize) {
                $transparent_color = imagecolorsforindex($image, $transparency);
                $transparency = imagecolorallocate($image_resized, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                imagefill($image_resized, 0, 0, $transparency);
                imagecolortransparent($image_resized, $transparency);
            } elseif ($info[2] == IMAGETYPE_PNG) {
                imagealphablending($image_resized, false);
                $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
                imagefill($image_resized, 0, 0, $color);
                imagesavealpha($image_resized, true);
            }
        }
        imagecopyresampled($image_resized, $image, 0, 0, $cropWidth, $cropHeight, $final_width, $final_height, $width_old - 2 * $cropWidth, $height_old - 2 * $cropHeight);


        # Taking care of original, if needed
        if ($delete_original) {
            if ($use_linux_commands) exec('rm ' . $file);
            else @unlink($file);
        }
        # Preparing a method of providing result
        switch (strtolower($output)) {
            case 'browser':
                $mime = image_type_to_mime_type($info[2]);
                header("Content-type: $mime");
                $output = NULL;
                break;
            case 'file':
                $output = $file;
                break;
            case 'return':
                return $image_resized;
                break;
            default:
                break;
        }

        # Writing image according to type to the output destination and image quality
        switch ($info[2]) {
            case IMAGETYPE_GIF:
                imagegif($image_resized, $output);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($image_resized, $output, $quality);
                break;
            case IMAGETYPE_PNG:
                $quality = 9 - (int)((0.9 * $quality) / 10.0);
                imagepng($image_resized, $output, $quality);
                break;
            default:
                return false;
        }
        return true;
    }

    public function sync(Request $request)
    {
        $data = $request->all();
        $user = $this->getUserFromToken();
        if ($user instanceof User) {
            if (isset($data['email'])) {
                $validator = Validator::make($request->all(), [
                    'email' => 'email',
                ]);
                if ($validator->fails()) {
                    return $this->getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
                }
            }
            //avatar
            if ($request->file('avatar')) {
                $imageName = time() . '.' . $request->file('avatar')->getClientOriginalExtension();
                $request->file('avatar')->move(base_path() . '/public/uploads/images/avatars/', $imageName);
                $data['avatar_url'] = $imageName;
            }
            unset($data[User::ID]);
            unset($data[User::USER_PASS]);
            unset($data[User::USER_LOGIN]);
            $user->update($data);

            $data['data'];


            return $this->getReturnMessage(self::SUCCESS, ['user' => $user], null);
        } else {
            return $user;
        }
    }

    public function change_password(Request $request)
    {
        $data = $request->all();
        $user = $this->getUserFromToken();
        if ($user instanceof User) {
            $validator = Validator::make($request->all(), [
                'password' => 'required|min:6',
                'new_password' => 'required|min:6',
            ]);

            if ($validator->fails()) {
                return $this->getReturnMessage(self::INVALID_PARAMS, null, $validator->errors()->all());
            }
            if (!password_verify($data['password'], $user->user_pass)) {
                return $this->getReturnMessage(self::LOGIN_FAIL, null, null);
            }
//            $data['password'] =
//                Hash::make($request->all()['new_password']);
            $user->update([
                User::USER_PASS => password_hash($data['new_password'], PASSWORD_DEFAULT)
            ]);
            return $this->getReturnMessage(self::SUCCESS, ['user' => $user], null);
        } else {
            return $user;
        }
    }


    /**
     * @param $user
     */
    public function filterUser($user)
    {
        if ($user->email_activation && strlen($user->email_activation) == 0) {
            $user->email_activation = 0;
        }
    }

    /**
     * @param $data
     * @param $user
     */
    public function authWithDeviceToken($data, $user)
    {
        DeviceToken::where(DeviceToken::DEVICE_TOKEN, '=', $data[DeviceToken::DEVICE_TOKEN])->delete();

        return DeviceToken::create([
            DeviceToken::USER_ID => $user->id,
            DeviceToken::DEVICE_TOKEN => $data[DeviceToken::DEVICE_TOKEN],
            DeviceToken::OS => $data[DeviceToken::OS],
        ]);
    }

    /**
     * @param $device_token
     * @return string
     */
    public function createJWT($device_token)
    {
        return JWT::encode($device_token, self::HARPA_SECRET_SERVER_KEY);
    }


}

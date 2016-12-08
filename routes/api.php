<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::post('/users/register', ['uses' => 'UserController@register']);
Route::post('/users/login', ['uses' => 'UserController@login']);
Route::post('/users/forgot_password', ['uses' => 'UserController@forgot_password']);
Route::post('/users/social', ['uses' => 'UserController@social']);
Route::get('/users/get_profile', ['uses' => 'UserController@get_profile']);
Route::post('/users/update_profile', ['uses' => 'UserController@update_profile']);
Route::post('/users/change_password', ['uses' => 'UserController@change_password']);
Route::post('/users/register', ['uses' => 'UserController@register']);

Route::get('/users/get_all', ['uses' => 'UserController@get_all']);
Route::get('/users/search', ['uses' => 'UserController@search']);
Route::get('/users/user_detail', ['uses' => 'UserController@user_detail']);



Route::get('/lists/get_user_list', ['uses' => 'SongListController@get_user_list']);
Route::post('/lists/create_list', ['uses' => 'SongListController@create_song_list']);
Route::get('/lists/get_list_detail', ['uses' => 'SongListController@get_list_detail']);
Route::post('/lists/update_list', ['uses' => 'SongListController@update_list']);
Route::get('/lists/delete_list', ['uses' => 'SongListController@delete_list']);

Route::post('/lists/share_list', ['uses' => 'SongListController@share_list']);
Route::get('/lists/get_follow_list', ['uses' => 'SongListController@get_follow_list']);
Route::get('/lists/get_share_list_request', ['uses' => 'SongListController@get_share_list_request']);


Route::post('/follows/follow', ['uses' => 'FollowController@follow']);
Route::post('/follows/unfollow', ['uses' => 'FollowController@unfollow']);
Route::get('/follows/get_follower', ['uses' => 'FollowController@get_follower']);
Route::get('/follows/get_following', ['uses' => 'FollowController@get_following']);

Route::get('/posts/get_home', ['uses' => 'PostController@get_home']);
Route::get('/articles/article_detail', ['uses' => 'ArticlesController@get_article_detail']);

Route::post('/requests/sync', ['uses' => 'RequestController@sync']);

Route::get('/requests/init', ['uses' => 'RequestController@init']);
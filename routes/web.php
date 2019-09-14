<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('/login', 'Auth\LoginController@login')->name('login-process');

Route::get('/', 'HomeController@index')->name('home');
Route::post('/post', 'CommentController@store')->name('post');
Route::delete('/delete', 'CommentController@destroy')->name('delete');
Route::post('/like', 'CommentController@like')->name('like');

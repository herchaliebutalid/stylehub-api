<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| StyleHub Root
|--------------------------------------------------------------------------
|
| StyleHub does not use Laravel's default welcome page.
|
| Opening:
|
| http://127.0.0.1:8000
|
| will automatically redirect to the Administrator login page:
|
| http://127.0.0.1:8000/admin/login
|
*/

Route::redirect(
    '/',
    '/admin/login'
);


/*
|--------------------------------------------------------------------------
| Administrator Web Application
|--------------------------------------------------------------------------
|
| The Administrator frontend is powered by Vue.
|
| All /admin routes that do not belong to the API load the Administrator
| Vue application.
|
| Examples:
|
| /admin/login
| /admin/dashboard
| /admin/shops
| /admin/users
| /admin/appointments
|
| Administrator API routes remain under:
|
| /api/v1/admin/*
|
*/

Route::get(
    '/admin/{any?}',
    function () {
        return view('admin');
    }
)
    ->where(
        'any',
        '.*'
    )
    ->name(
        'admin.app'
    );
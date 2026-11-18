<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/dashboard');

// ---------- Authentification ----------
Route::view('/login', 'auth.login')->name('login');
Route::view('/forgot-password', 'auth.forgot-password');
Route::view('/reset-password', 'auth.reset-password');
Route::view('/change-password', 'auth.change-password');

// ---------- Application ----------
Route::view('/dashboard', 'dashboard');

Route::view('/categories', 'categories.index');
Route::view('/projects', 'projects.index');
Route::view('/projects/{id}', 'projects.show')->whereNumber('id');

Route::view('/tasks', 'tasks.index');
Route::view('/agenda', 'agenda.index');
Route::view('/planning', 'planning.index');
Route::view('/space', 'space.index');
Route::view('/assistant', 'assistant.index');
Route::view('/decisions', 'registers.index')->name('decisions');
Route::view('/meeting-minutes', 'registers.index')->name('meeting-minutes');
Route::view('/evaluation', 'evaluation.index');

Route::view('/users', 'users.index');
Route::view('/settings', 'settings.index');
Route::view('/activity', 'activity.index');

Route::view('/notifications', 'notifications.index');
Route::view('/profile', 'profile.index');
Route::view('/search', 'search.index');

<?php

use App\Http\Controllers\DownloadConnector;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::get('/connector/download', DownloadConnector::class)
    ->middleware('throttle:30,1')
    ->name('connector.download');
Route::post('/invitations/{token}/register', [InvitationController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('invitations.register');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('invitations.accept');

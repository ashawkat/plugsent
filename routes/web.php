<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\InvitationController;

Route::get('/', function () {
    return redirect('/app');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('invitations.accept');

<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Fortify;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    public function __invoke()
    {
        Fortify::authenticateUsing(function ($request) {
            $user = User::where('email', $request->email)->first();

            if ($user &&
                Hash::check($request->password, $user->password) &&
                $user->role === $request->role) {
                return $user;
            }

            // Jika user ditemukan tapi role tidak cocok, berikan error spesifik
            if ($user && Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'role' => ['Role yang dipilih tidak sesuai dengan akun Anda.'],
                ]);
            }

            return null;
        });
    }
}
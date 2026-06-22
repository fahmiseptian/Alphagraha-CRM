<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EspoAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(protected EspoAuth $espoAuth)
    {
    }

    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginInput = $request->input('login');
        $password = $request->input('password');

        // Autentikasi terhadap tabel `user` EspoCRM.
        $user = $this->espoAuth->attempt($loginInput, $password);

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => 'Email/username atau kata sandi salah, atau akun tidak aktif.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

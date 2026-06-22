<?php

namespace App\Http\Controllers;

use App\Services\EspoPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    /**
     * Profil (nama/email) dikelola di EspoCRM. Di sini pengguna hanya
     * dapat mengganti kata sandi (disimpan kembali ke tabel `user`
     * memakai algoritma hashing EspoCRM agar tetap kompatibel).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! EspoPassword::verify($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini salah.']);
        }

        DB::table('user')->where('id', $user->id)->update([
            'password' => EspoPassword::hash($request->input('password')),
        ]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}

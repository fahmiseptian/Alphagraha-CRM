<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Services\EspoPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $user->load('profile');

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'job_position' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'required_with:password'],
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        $profile = UserProfile::ensureForUser($user->id, $user->isSales());
        $profile->job_position = $validated['job_position'] ?? null;
        $profile->save();

        if (! $request->filled('password')) {
            return back()->with('success', 'Job position berhasil disimpan.');
        }

        if (! EspoPassword::verify($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }

        DB::table('user')->where('id', $user->id)->update([
            'password' => EspoPassword::hash($request->input('password')),
        ]);

        return back()->with('success', 'Profil dan password berhasil diperbarui.');
    }

    public function uploadSignature(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'signature' => ['required', File::image()->max(2048)],
        ]);

        $file = $request->file('signature');
        $path = $file->storeAs('signatures', $user->id.'.'.$file->getClientOriginalExtension(), 'public');

        $profile = UserProfile::ensureForUser($user->id, $user->isSales());

        if ($profile->signature_path && $profile->signature_path !== $path) {
            Storage::disk('public')->delete($profile->signature_path);
        }

        $profile->signature_path = $path;
        $profile->save();

        return back()->with('success', 'Tanda tangan digital berhasil diunggah.');
    }

    public function destroySignature(Request $request)
    {
        $user = $request->user();
        $profile = UserProfile::query()->where('user_id', $user->id)->first();

        if ($profile?->signature_path) {
            Storage::disk('public')->delete($profile->signature_path);
            $profile->update(['signature_path' => null]);
        }

        return back()->with('success', 'Tanda tangan digital dihapus.');
    }
}

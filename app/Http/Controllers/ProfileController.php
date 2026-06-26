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

        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! EspoPassword::verify($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        DB::table('user')->where('id', $user->id)->update([
            'password' => EspoPassword::hash($request->input('password')),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function uploadSignature(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'signature' => ['required', File::image()->max(2048)],
        ]);

        $file = $request->file('signature');
        $path = $file->storeAs('signatures', $user->id.'.'.$file->getClientOriginalExtension(), 'public');

        $profile = UserProfile::query()->firstOrNew(['user_id' => $user->id]);

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

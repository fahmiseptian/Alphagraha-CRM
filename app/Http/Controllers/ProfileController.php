<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Services\EspoPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $user->load('profile');

        return view('profile.edit', [
            'user' => $user,
            'signatureCompanies' => UserProfile::signatureCompanyLabels(),
        ]);
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

        if (! $user->isSales() && ! $user->isSuperAdmin() && ! $user->isAdmin()) {
            abort(403, 'Hanya sales yang perlu mengunggah tanda tangan.');
        }

        $request->validate([
            'company' => ['required', Rule::in(UserProfile::signatureCompanyKeys())],
            'signature' => ['required', File::image()->max(2048)],
        ]);

        $companyKey = UserProfile::resolveCompanyKey($request->input('company'));
        $file = $request->file('signature');
        $path = $file->storeAs(
            'signatures/'.$companyKey,
            $user->id.'.'.$file->getClientOriginalExtension(),
            'public'
        );

        $profile = UserProfile::ensureForUser($user->id, $user->isSales());
        $oldPath = $profile->signaturePathFor($companyKey);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $profile->setSignaturePathFor($companyKey, $path);
        $profile->save();

        $label = UserProfile::signatureCompanyLabels()[$companyKey] ?? $companyKey;

        return back()->with('success', 'Tanda tangan '.$label.' berhasil diunggah.');
    }

    public function destroySignature(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'company' => ['required', Rule::in(UserProfile::signatureCompanyKeys())],
        ]);

        $companyKey = UserProfile::resolveCompanyKey($request->input('company'));
        $profile = UserProfile::query()->where('user_id', $user->id)->first();

        if ($profile) {
            $path = $profile->signaturePathFor($companyKey);
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $profile->setSignaturePathFor($companyKey, null);
            $profile->save();
        }

        $label = UserProfile::signatureCompanyLabels()[$companyKey] ?? $companyKey;

        return back()->with('success', 'Tanda tangan '.$label.' dihapus.');
    }
}

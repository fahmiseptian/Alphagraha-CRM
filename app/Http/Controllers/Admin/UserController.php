<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EspoUserManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Kelola pengguna langsung pada tabel `user` EspoCRM.
 * Role disimpan sebagai kolom `type` (admin / regular).
 */
class UserController extends Controller
{
    public function __construct(protected EspoUserManager $manager)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));

        $users = User::query()
            ->with('profile')
            ->whereIn('type', ['regular', 'admin'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('user_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create()
    {
        $user = new User(['type' => 'regular', 'is_active' => true]);

        return view('admin.users.create', compact('user'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['password'] = $request->input('password');

        $this->manager->create($data);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $user->load('profile');

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $user->load('profile');
        $data = $this->validateData($request, $user);

        $this->manager->update($user, $data);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->user_name === 'admin') {
            return back()->with('error', 'The main administrator account cannot be deleted.');
        }

        $this->manager->delete($user);

        return back()->with('success', 'User deleted.');
    }

    /**
     * Validasi & normalisasi input form menjadi data siap simpan.
     */
    protected function validateData(Request $request, ?User $user = null): array
    {
        $userNameRule = Rule::unique('user', 'user_name')->where(fn ($q) => $q->where('deleted', 0));
        if ($user) {
            $userNameRule->ignore($user->id, 'id');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:50', $userNameRule],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'sales'])],
            'sales_code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('crm_user_profiles', 'sales_code')->ignore(
                    optional($user?->profile)->id
                ),
            ],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(6)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'sales_code.required' => 'Sales Code wajib diisi.',
            'sales_code.unique' => 'Sales Code sudah dipakai user lain.',
            'sales_code.regex' => 'Sales Code hanya boleh huruf dan angka.',
        ]);

        return [
            'name' => $validated['name'],
            'user_name' => $validated['user_name'],
            'email' => $validated['email'] ?? null,
            'type' => $validated['role'] === 'admin' ? 'admin' : 'regular',
            'password' => $validated['password'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sales_code' => strtoupper(trim($validated['sales_code'])),
        ];
    }
}

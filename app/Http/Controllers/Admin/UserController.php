<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\EspoUserManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Kelola pengguna langsung pada tabel `user` EspoCRM.
 * Role aplikasi disimpan di crm_user_profiles.app_role.
 * Daftar user difilter per role (submenu Administration).
 */
class UserController extends Controller
{
    public function __construct(protected EspoUserManager $manager)
    {
    }

    public function index(Request $request)
    {
        $role = $this->resolveRoleFilter($request);
        $search = trim((string) $request->get('search'));

        $users = User::query()
            ->with('profile')
            ->whereIn('type', ['regular', 'admin'])
            ->when($role, function ($query) use ($role) {
                $query->whereHas('profile', fn ($q) => $q->where('app_role', $role));
            })
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

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'roleLabel' => User::ROLES[$role] ?? 'Users',
        ]);
    }

    public function create(Request $request)
    {
        $role = $this->resolveRoleFilter($request) ?? User::ROLE_SALES;
        $user = new User(['type' => User::espoTypeForRole($role), 'is_active' => true]);

        return view('admin.users.create', [
            'user' => $user,
            'role' => $role,
            'roleLabel' => User::ROLES[$role] ?? 'User',
            'lockRole' => true,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['password'] = $request->input('password');

        $this->manager->create($data);

        return redirect()
            ->route('users.index', ['role' => $data['app_role']])
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $user->load('profile');
        $role = $user->role;

        return view('admin.users.edit', [
            'user' => $user,
            'role' => $role,
            'roleLabel' => User::ROLES[$role] ?? 'User',
            'lockRole' => false,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $user->load('profile');
        $data = $this->validateData($request, $user);

        $this->manager->update($user, $data);

        return redirect()
            ->route('users.index', ['role' => $data['app_role']])
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->user_name === 'admin') {
            return back()->with('error', 'The main administrator account cannot be deleted.');
        }

        $role = $user->role;
        $this->manager->delete($user);

        return redirect()
            ->route('users.index', ['role' => $role])
            ->with('success', 'User deleted.');
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
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'sales_code' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_SALES),
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('crm_user_profiles', 'sales_code')->ignore(
                    optional($user?->profile)->id
                ),
            ],
            'sales_target' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_SALES),
                'nullable',
                'numeric',
                'min:0',
            ],
            'sales_target_period' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_SALES),
                'nullable',
                Rule::in(array_keys(UserProfile::TARGET_PERIODS)),
            ],
            'sales_target_deadline' => [
                'nullable',
                'date',
            ],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(6)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'sales_code.required' => 'Sales Code wajib diisi untuk role Sales.',
            'sales_code.unique' => 'Sales Code sudah dipakai user lain.',
            'sales_code.regex' => 'Sales Code hanya boleh huruf dan angka.',
            'sales_target.required' => 'Sales Target wajib diisi untuk role Sales.',
            'sales_target_period.required' => 'Periode target wajib diisi untuk role Sales.',
        ]);

        $appRole = $validated['role'];

        return [
            'name' => $validated['name'],
            'user_name' => $validated['user_name'],
            'email' => $validated['email'] ?? null,
            'app_role' => $appRole,
            'type' => User::espoTypeForRole($appRole),
            'password' => $validated['password'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sales_code' => ! empty($validated['sales_code'])
                ? strtoupper(trim($validated['sales_code']))
                : null,
            'sales_target' => $appRole === User::ROLE_SALES
                ? (float) ($validated['sales_target'] ?? UserProfile::DEFAULT_SALES_TARGET)
                : null,
            'sales_target_period' => $appRole === User::ROLE_SALES
                ? ($validated['sales_target_period'] ?? UserProfile::TARGET_PERIOD_1_YEAR)
                : null,
            'sales_target_deadline' => $appRole === User::ROLE_SALES
                ? (! empty($validated['sales_target_deadline']) ? $validated['sales_target_deadline'] : null)
                : null,
        ];
    }

    /**
     * Ambil filter role dari query; default Sales.
     */
    protected function resolveRoleFilter(Request $request): string
    {
        $role = (string) $request->get('role', User::ROLE_SALES);

        return isset(User::ROLES[$role]) ? $role : User::ROLE_SALES;
    }
}

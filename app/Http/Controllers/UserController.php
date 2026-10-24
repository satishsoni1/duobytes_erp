<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('users.index', [
            'users' => User::when($request->query('role'), fn ($q, $v) => $q->where('role', $v))
                ->withCount(['leads as open_leads' => fn ($q) => $q->open()])
                ->orderByDesc('is_active')->orderBy('name')->paginate(30)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['role' => 'sales', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if ($user->is($request->user()) && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->with('error', 'You cannot remove your own admin access.');
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete yourself.');
        }
        // Keep history intact: users are deactivated, not hard-deleted.
        $user->update(['is_active' => false, 'receives_leads' => false]);

        return back()->with('success', 'User deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(config('crm.roles')))],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'receives_leads' => $request->boolean('receives_leads'),
        ];
    }
}

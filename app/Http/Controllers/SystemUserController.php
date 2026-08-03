<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SystemUserController extends Controller
{
    public function index(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = User::query()->orderBy('username');

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder->where('username', 'like', '%' . $request->string('q') . '%')
                    ->orWhere('name', 'like', '%' . $request->string('q') . '%');
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        return view('administration.master.system-users', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'role'),
        ]);
    }

    public function store(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'role' => ['required', 'in:Administrator,Leader'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'role' => $data['role'],
            'email' => $data['username'] . '@wims.local',
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', 'System user created.');
    }

    public function update(Request $request, User $user)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'role' => ['required', 'in:Administrator,Leader'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'role' => $data['role'],
            'email' => $data['username'] . '@wims.local',
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return back()->with('success', 'System user updated.');
    }

    public function destroy(User $user)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        if (Auth::id() === $user->id) {
            return back()->with('success', 'Cannot delete current login user.');
        }

        $user->delete();

        return back()->with('success', 'System user deleted.');
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}

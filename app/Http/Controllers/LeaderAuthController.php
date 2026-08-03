<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaderAuthController extends Controller
{
    public function create(Request $request)
    {
        if (Auth::check() && Auth::user()->role === 'Leader') {
            return redirect()->route('leader.panel');
        }

        return view('leader.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'role' => 'Leader',
        ])) {
            $request->session()->regenerate();

            return redirect()->route('leader.panel');
        }

        return back()
            ->withInput($request->only('username'))
            ->with('leader_login_error', 'Invalid username or password.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('leader.login');
    }
}

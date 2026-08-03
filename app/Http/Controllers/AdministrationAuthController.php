<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdministrationAuthController extends Controller
{
    public function create(Request $request)
    {
        if (Auth::check() && Auth::user()->role === 'Administrator') {
            return redirect()->route('administration.dashboard');
        }

        return view('administration.login');
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
            'role' => 'Administrator',
        ])) {
            $request->session()->regenerate();

            return redirect()->route('administration.dashboard');
        }

        return back()
            ->withInput($request->only('username'))
            ->with('administration_login_error', 'Invalid username or password.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('administration.login');
    }
}

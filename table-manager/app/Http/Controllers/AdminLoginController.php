<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');
        $expectedUser = (string) config('admin.username');
        $expectedPass = (string) config('admin.password');

        $userOk = strlen($username) === strlen($expectedUser) && hash_equals($expectedUser, $username);
        $passOk = strlen($password) === strlen($expectedPass) && hash_equals($expectedPass, $password);

        if ($userOk && $passOk) {
            $request->session()->regenerate();
            $request->session()->put('admin_authenticated', true);

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors([
            'username' => 'Nieprawidłowy login lub hasło.',
        ])->onlyInput('username');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }
}

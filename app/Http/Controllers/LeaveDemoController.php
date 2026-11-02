<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveDemoController extends Controller
{
    /**
     * End the demo session and send the visitor to the signup form, so they
     * can create a real club. The sandbox itself is left for the hourly prune.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()?->isDemoSandbox()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->to(route('home').'#signup');
    }
}

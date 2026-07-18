<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\DevelopmentLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DevelopmentSessionController extends Controller
{
    public function __invoke(Request $request, DevelopmentLogin $developmentLogin): RedirectResponse
    {
        abort_unless($developmentLogin->isEnabled(), 404);

        $user = $developmentLogin->user();

        abort_unless(
            $user instanceof User,
            409,
            '開発ログイン対象を特定できません。DEV_LOGIN_USER_EMAIL を設定してください。',
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

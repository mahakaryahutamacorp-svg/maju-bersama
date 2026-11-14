<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik."]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            $remaining = RateLimiter::remaining($throttleKey, self::MAX_ATTEMPTS);

            $message = $remaining > 0
                ? "Email atau password tidak valid. Sisa percobaan: {$remaining}."
                : 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.';

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => $message]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Redirect cashier to POS, others to backoffice
        if ($request->user()->role === 'cashier') {
            return redirect('/pos');
        }

        return redirect()->intended('/backoffice');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();
    }
}

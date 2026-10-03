<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $clientId = config('services.google.client_id');

        abort_unless($clientId && config('services.google.client_secret'), 503, 'Google sign-in is not configured on the server.');

        $state = Str::random(64);
        session()->put('google_oauth_state', $state);

        $redirectUri = config('services.google.redirect') ?: route('auth.google.callback');
        $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = session()->pull('google_oauth_state');
        $receivedState = $request->query('state');

        if (! is_string($expectedState) || ! is_string($receivedState) || ! hash_equals($expectedState, $receivedState)) {
            return $this->frontendRedirect(['google_error' => 'invalid_state']);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->frontendRedirect(['google_error' => 'authorization_failed']);
        }

        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'code' => $request->query('code'),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('services.google.redirect') ?: route('auth.google.callback'),
                ]);

            if (! $tokenResponse->successful() || ! $tokenResponse->json('access_token')) {
                return $this->frontendRedirect(['google_error' => 'token_exchange_failed']);
            }

            $profileResponse = Http::withToken($tokenResponse->json('access_token'))
                ->acceptJson()
                ->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');
        } catch (ConnectionException) {
            return $this->frontendRedirect(['google_error' => 'provider_unavailable']);
        }

        $googleProfile = $profileResponse->json();

        if (! $profileResponse->successful()
            || ! is_array($googleProfile)
            || ! filter_var($googleProfile['email'] ?? null, FILTER_VALIDATE_EMAIL)
            || ! filter_var($googleProfile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || empty($googleProfile['sub'])) {
            return $this->frontendRedirect(['google_error' => 'profile_not_verified']);
        }

        $user = DB::transaction(function () use ($googleProfile): User {
            $email = strtolower($googleProfile['email']);
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => Str::limit(trim($googleProfile['name'] ?? ''), 255, '') ?: Str::before($email, '@'),
                    'phone' => null,
                    'password' => Str::random(64),
                ]
            );

            if ($user->wasRecentlyCreated) {
                $user->forceFill(['email_verified_at' => now()])->save();

                if ($user->is_active) {
                    $user->assignRole('buyer');
                }
            }

            return $user;
        });

        if (! $user->is_active) {
            return $this->frontendRedirect(['google_error' => 'account_inactive']);
        }

        $plainCode = Str::random(64);
        DB::table('google_auth_codes')->where('expires_at', '<=', now())->delete();
        DB::table('google_auth_codes')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainCode),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->frontendRedirect([
            'google_code' => $plainCode,
            'phone_required' => $user->phone === null,
        ]);
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $user = DB::transaction(function () use ($validated): ?User {
            $loginCode = DB::table('google_auth_codes')
                ->where('token_hash', hash('sha256', $validated['code']))
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $loginCode) {
                return null;
            }

            DB::table('google_auth_codes')->where('id', $loginCode->id)->delete();

            return User::find($loginCode->user_id);
        });

        if (! $user || ! $user->is_active) {
            return ApiResponse::error('The Google sign-in code is invalid or has expired.', 401);
        }

        $user->load(['roles', 'sellerProfile', 'rider']);

        return ApiResponse::success(
            message: 'Google sign-in successful.',
            data: [
                'user' => $user,
                'account' => [
                    'seller_application_status' => $user->sellerProfile?->status ?? 'not_applied',
                    'seller_dashboard_available' => $user->isApprovedSeller(),
                    'rider_dashboard_available' => $user->is_active
                        && $user->hasRole('rider')
                        && $user->rider !== null,
                    'phone_required' => $user->phone === null,
                ],
                'token' => $user->createToken('orderme-google-login')->plainTextToken,
                'token_type' => 'Bearer',
            ]
        );
    }

    /**
     * @param  array<string, string|bool>  $query
     */
    private function frontendRedirect(array $query): RedirectResponse
    {
        $url = rtrim(config('app.frontend_url'), '/').'?'.http_build_query($query);

        return redirect()->away($url);
    }
}

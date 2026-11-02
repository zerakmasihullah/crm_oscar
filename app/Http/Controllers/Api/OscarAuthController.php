<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SystemControl;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OscarAuthController extends Controller
{
    public function register(Request $request)
    {
        if (! SystemControl::registrationEnabled()) {
            return response()->json([
                'status' => false,
                'message' => 'Registration is currently disabled',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'nullable|string|max:30',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'area_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:500',
            'password' => 'required|string|min:6',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('profile-images', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'area_code' => $request->area_code,
            'address' => $request->address,
            'password' => $request->password,
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
            'is_staff' => false,
            'image' => $imagePath,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully',
            'user' => $this->presentUser($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->login)->first();
        if ($user && ! $user->is_active) {
            return response()->json(['message' => 'User account is not active'], 403);
        }

        if (! Auth::attempt(['email' => $request->login, 'password' => $request->password])) {
            return response()->json(['message' => 'Invalid login details'], 401);
        }

        $user = User::where('email', $request->login)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'user' => $this->presentUser($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    public function user_profile(Request $request, $id)
    {
        $authId = (int) $request->user()->id;
        if ($authId !== (int) $id) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $user = User::findOrFail($id);

        return response()->json([
            'status' => true,
            'user' => $this->presentUser($user),
        ], 200);
    }

    public function edit_user(Request $request, $id)
    {
        $authId = (int) $request->user()->id;
        if ($authId !== (int) $id) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$id,
            'phone_number' => 'nullable|string|max:30',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'area_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:6',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $user->image = $request->file('image')->store('profile-images', 'public');
        }

        $user->name = $request->name ?? $user->name;
        $user->email = $request->email ?? $user->email;
        $user->phone_number = $request->phone_number ?? $user->phone_number;
        $user->latitude = $request->latitude ?? $user->latitude;
        $user->longitude = $request->longitude ?? $user->longitude;
        $user->area_code = $request->area_code ?? $user->area_code;
        if ($request->has('address')) {
            $user->address = $request->address;
        }
        if ($request->filled('password')) {
            $user->password = $request->password;
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => $this->presentUser($user->fresh()),
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        if (! SystemControl::resetPasswordEnabled()) {
            return response()->json([
                'status' => false,
                'message' => 'Password reset is currently disabled',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT || $status === Password::INVALID_USER) {
            return response()->json([
                'status' => true,
                'message' => 'A link sent! Check your email.',
            ], 200);
        }

        Log::warning('forgot password failed: '.$status);

        return response()->json([
            'status' => false,
            'message' => 'Unable to send reset link. Please try again.',
        ], 500);
    }

    public function google(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->input('id_token'),
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Google token',
                ], 400);
            }

            $payload = $response->json();
            $aud = (string) ($payload['aud'] ?? '');
            $allowed = $this->googleAllowedAudiences();
            if ($allowed !== [] && ($aud === '' || ! in_array($aud, $allowed, true))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Google token audience mismatch',
                ], 400);
            }

            $email = $payload['email'] ?? null;
            if (! $email) {
                return response()->json([
                    'status' => false,
                    'message' => 'Google token missing email',
                ], 400);
            }

            $name = $payload['name']
                ?? trim(($payload['given_name'] ?? '').' '.($payload['family_name'] ?? ''))
                ?: 'Google User';

            $googleId = isset($payload['sub']) ? (string) $payload['sub'] : null;

            return $this->issueSocialUser($email, $name, [
                'google_id' => $googleId,
            ]);
        } catch (\Throwable $e) {
            Log::error('google social login failed: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Google login failed',
            ], 500);
        }
    }

    public function apple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
            'user' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payload = $this->verifyAppleIdentityToken((string) $request->input('id_token'));
            $sub = (string) ($payload['sub'] ?? '');
            if ($sub === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Apple token',
                ], 400);
            }

            $email = $payload['email']
                ?? data_get($request->input('user'), 'email');

            $first = data_get($request->input('user'), 'name.firstName');
            $last = data_get($request->input('user'), 'name.lastName');
            $name = trim(($first ?? '').' '.($last ?? ''));
            if ($name === '') {
                $name = 'Apple User';
            }

            return $this->issueSocialUser(
                is_string($email) && $email !== '' ? $email : null,
                $name,
                ['apple_id' => $sub],
            );
        } catch (\Throwable $e) {
            Log::error('apple social login failed: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Apple login failed',
            ], 500);
        }
    }

    /**
     * @param  array{google_id?: ?string, apple_id?: ?string}  $ids
     */
    private function issueSocialUser(?string $email, string $name, array $ids = [])
    {
        $googleId = $ids['google_id'] ?? null;
        $appleId = $ids['apple_id'] ?? null;

        $user = null;
        if ($appleId) {
            $user = User::query()->where('apple_id', $appleId)->first();
        }
        if (! $user && $googleId) {
            $user = User::query()->where('google_id', $googleId)->first();
        }
        if (! $user && $email) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user) {
            if (! $email && $appleId) {
                $email = 'apple_'.$appleId.'@privaterelay.appleid.com';
            }
            if (! $email) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to resolve social account email',
                ], 400);
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(32),
                'role' => User::ROLE_CUSTOMER,
                'is_active' => true,
                'is_staff' => false,
                'google_id' => $googleId,
                'apple_id' => $appleId,
            ]);
        } else {
            $dirty = false;
            if ($googleId && empty($user->google_id)) {
                $user->google_id = $googleId;
                $dirty = true;
            }
            if ($appleId && empty($user->apple_id)) {
                $user->apple_id = $appleId;
                $dirty = true;
            }
            if ($dirty) {
                $user->save();
            }
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'User account is not active'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'user' => $this->presentUser($user),
            'token' => $token,
            'access' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    private function presentUser(User $user): User
    {
        $user->setAttribute('image', $this->publicImageUrl($user->image));

        return $user;
    }

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(Storage::disk('public')->url($path));
    }

    /**
     * @return list<string>
     */
    private function googleAllowedAudiences(): array
    {
        $ids = [
            config('services.google.client_id'),
            config('services.google.ios_client_id'),
            config('services.google.android_client_id'),
            config('services.google.web_client_id'),
        ];

        return array_values(array_unique(array_filter(array_map(
            static fn ($v) => is_string($v) && $v !== '' ? $v : null,
            $ids,
        ))));
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyAppleIdentityToken(string $idToken): array
    {
        $jwks = Http::timeout(10)->get('https://appleid.apple.com/auth/keys')->json();
        if (! is_array($jwks) || empty($jwks['keys'])) {
            throw new \RuntimeException('Unable to load Apple JWKS');
        }

        $keys = JWK::parseKeySet($jwks);
        $decoded = JWT::decode($idToken, $keys);
        $payload = (array) $decoded;

        if (($payload['iss'] ?? null) !== 'https://appleid.apple.com') {
            throw new \RuntimeException('Invalid Apple token issuer');
        }

        $aud = (string) ($payload['aud'] ?? '');
        $allowedAud = array_values(array_filter([
            config('services.apple.client_id'),
            config('services.apple.bundle_id'),
        ]));
        if ($allowedAud !== [] && ! in_array($aud, $allowedAud, true)) {
            throw new \RuntimeException('Invalid Apple token audience');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < time()) {
            throw new \RuntimeException('Apple token expired');
        }

        return $payload;
    }
}

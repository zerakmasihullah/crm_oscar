<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SystemControl;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OscarAuthController extends Controller
{

    public function register(Request $request)
    {
        // Validate incoming data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'nullable|string|max:15',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'area_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:500',
            'password' => 'required|string|min:6',
            'image' => 'nullable|image|max:2048',
        ]);

        // Check validation failure
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('profile-images', 'public'); // Save image in 'storage/app/public/user'
        }

        // Create the user
        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'area_code' => $request->area_code,
            'address' => $request->address,
            'password' => Hash::make($request->password), // Hash the password
            'role' => 2,
            'image' => $imagePath,
        ]);

        $user['image'] = url('https://oscarlimerick.com/storage/' . $user['image']);

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);
    }


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|email', // Validate 'login' as email
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Check if the user is active
        $user = User::where('email', $request->login)->first();
        if ($user && !$user->is_active) {
            return response()->json(['message' => 'User account is not active'], 403);
        }

        // Attempt login using the 'login' (email) field and password
        if (!Auth::attempt(['email' => $request->login, 'password' => $request->password])) {
            return response()->json(['message' => 'Invalid login details'], 401);
        }

        // Get the authenticated user
        $user = User::where('email', $request->login)->firstOrFail();
        $user['image'] = url('https://oscarlimerick.com/storage/' . $user['image']);
        // Create an auth token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }


    public function logout(Request $request)
    {
        // Revoke the current user's token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    public function user_profile($id)
    {
        $user = User::find($id);
        if ($user->image) {
            $user['image'] = url('https://oscarlimerick.com/storage/' . $user['image']);
        }
        return response()->json([
            'status' => true,
            'user' => $user,
        ], 200);
    }



    public function edit_user(Request $request, $id)
    {
        // Find the user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Validate incoming data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone_number' => 'nullable|string|max:15',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'area_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update image if provided
        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($user->image) {
                \Storage::disk('public')->delete($user->image);
            }

            // Upload new image
            $user->image = $request->file('image')->store('profile-images', 'public');
        }

        // Update other fields
        $user->name = $request->name ?? $user->name;
        $user->email = $request->email ?? $user->email;
        $user->phone_number = $request->phone_number ?? $user->phone_number;
        $user->latitude = $request->latitude ?? $user->latitude;
        $user->longitude = $request->longitude ?? $user->longitude;
        $user->area_code = $request->area_code ?? $user->area_code;
        if ($request->has('address')) {
            $user->address = $request->address;
        }

        if ($request->password) {
            $user->password = Hash::make($request->password); // Hash the password
        }

        $user->save();

        $user['image'] = $user->image ? url('https://oscarlimerick.com/storage/' . $user->image) : null;

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => $user,
        ], 200);
    }

    /**
     * Mobile forgot-password — email a reset link (same broker as Fortify web).
     */
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

        // Always return a generic success so we don't leak account existence.
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

    /**
     * Google Sign-In — verify id_token then issue Sanctum token.
     */
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
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->input('id_token'),
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Google token',
                ], 400);
            }

            $payload = $response->json();
            $email = $payload['email'] ?? null;
            if (!$email) {
                return response()->json([
                    'status' => false,
                    'message' => 'Google token missing email',
                ], 400);
            }

            $name = $payload['name']
                ?? trim(($payload['given_name'] ?? '').' '.($payload['family_name'] ?? ''))
                ?: 'Google User';

            return $this->issueSocialUser($email, $name);
        } catch (\Throwable $e) {
            Log::error('google social login failed: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Google login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apple Sign-In — decode identity token payload (aud/sub/email) then issue token.
     */
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
            $parts = explode('.', (string) $request->input('id_token'));
            if (count($parts) < 2) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Apple token',
                ], 400);
            }

            $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
            $payload = json_decode($payloadJson ?: '{}', true) ?: [];
            $email = $payload['email']
                ?? data_get($request->input('user'), 'email');

            if (!$email) {
                // Apple may hide email after first login — use stable private relay from sub
                $sub = $payload['sub'] ?? null;
                if (!$sub) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid Apple token',
                    ], 400);
                }
                $email = 'apple_'.$sub.'@privaterelay.appleid.com';
            }

            $first = data_get($request->input('user'), 'name.firstName');
            $last = data_get($request->input('user'), 'name.lastName');
            $name = trim(($first ?? '').' '.($last ?? ''));
            if ($name === '') {
                $name = 'Apple User';
            }

            return $this->issueSocialUser($email, $name);
        } catch (\Throwable $e) {
            Log::error('apple social login failed: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Apple login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function issueSocialUser(string $email, string $name)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'role' => 2,
                'is_active' => true,
            ]);
        }

        if (isset($user->is_active) && !$user->is_active) {
            return response()->json(['message' => 'User account is not active'], 403);
        }

        if ($user->image) {
            $user['image'] = url('https://oscarlimerick.com/storage/'.$user->image);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'user' => $user,
            'token' => $token,
            'access' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

}

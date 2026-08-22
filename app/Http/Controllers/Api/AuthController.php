<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'purok_id' => ['required', 'exists:puroks,id'],
        ]);

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'role' => 'resident',
        ]);

        return $this->issueToken($user, 'mobile');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        return $this->issueToken($user, 'mobile');
    }

    public function logout(Request $request): JsonResponse
    {
        $header = $request->header('Authorization', '');
        $plain = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        if ($plain !== '') {
            ApiToken::where('token_hash', hash('sha256', $plain))->delete();
        }
        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $request->user()->load('purok')]);
    }

    private function issueToken(User $user, string $name): JsonResponse
    {
        $plain = Str::random(80);
        ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'name' => $name,
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['user' => $user->load('purok'), 'token' => $plain, 'token_type' => 'Bearer'],
        ]);
    }
}

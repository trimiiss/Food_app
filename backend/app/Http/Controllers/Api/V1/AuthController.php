<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminRegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->createUserWithToken($request->validated(), UserRole::Customer);
    }

    public function registerAdmin(AdminRegisterRequest $request): JsonResponse
    {
        return $this->createUserWithToken($request->validated(), UserRole::Admin);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            // Same message whether the email or the password was wrong, so the
            // endpoint can't be used to discover which emails are registered.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $this->tokenResponse($user);
    }

    public function logout(Request $request): Response
    {
        // Revoke only the token used for this request — other devices stay signed in.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createUserWithToken(array $data, UserRole $role): JsonResponse
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        // Role is set explicitly by the server — it is not mass assignable.
        $user->role = $role;
        $user->save();

        return $this->tokenResponse($user, 201);
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken('spa')->plainTextToken,
            ],
        ], $status);
    }
}

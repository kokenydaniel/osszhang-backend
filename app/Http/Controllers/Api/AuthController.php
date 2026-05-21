<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request)
    {
        return response()->json($this->authService->register($request->validated()));
    }

    public function login(LoginRequest $request)
    {
        $payload = $this->authService->login((string) $request->username, (string) $request->password);

        return response()->json($payload);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Sikeres kijelentkezés.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($this->authService->me($request->user()));
    }

    public function update(UpdateProfileRequest $request)
    {
        return response()->json($this->authService->updateProfile($request->user(), $request));
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        return response()->json($this->authService->changePassword($request->user(), $request->password));
    }
}

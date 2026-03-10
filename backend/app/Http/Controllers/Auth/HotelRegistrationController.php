<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HotelRegistrationService;

class HotelRegistrationController extends Controller
{
    protected $registrationService;

    public function __construct(HotelRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'hotel_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $result = $this->registrationService->registerHotel($validatedData);

        return response()->json([
            'message' => 'Hotel registered successfully.',
            'hotel' => $result['hotel'],
            'user' => $result['user'],
            'token' => $result['token'],
        ], 201);
    }
}

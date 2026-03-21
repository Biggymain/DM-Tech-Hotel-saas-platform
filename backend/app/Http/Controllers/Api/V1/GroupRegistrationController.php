<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\HotelGroup;
use App\Models\Hotel;
use App\Services\GroupRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupRegistrationController extends Controller
{
    public function __construct(private GroupRegistrationService $service) {}

    /**
     * POST /api/v1/auth/register-group
     * Public route — no auth, no tenant middleware.
     * Creates a HotelGroup + first Branch + GROUP_ADMIN user.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'group_name' => 'required|string|min:2|max:255',
            'hotel_name' => 'required|string|min:2|max:255',
            'owner_name' => 'required|string|min:2|max:255',
            'email'      => 'required|email|max:255|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'currency'   => 'nullable|string|size:3',
            'tax_rate'   => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $result = $this->service->registerGroup($validated);

            return response()->json([
                'message' => 'Organization registered successfully.',
                'group'   => $result['group'],
                'branch'  => $result['branch'],
                'user'    => $result['user'],
                'token'   => $result['token'],
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000' || $e->getCode() === '23505') { // Integrity constraint violation
                return response()->json([
                    'message' => 'Registration failed',
                    'errors'  => ['email' => ['This email is already registered.']]
                ], 422);
            }
            throw $e;
        }
    }
}

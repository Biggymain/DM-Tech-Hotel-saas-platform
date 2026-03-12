<?php

namespace App\Http\Controllers\API\V1;

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

        $result = $this->service->registerGroup($validated);

        return response()->json([
            'message' => 'Organization registered successfully.',
            'group'   => $result['group'],
            'branch'  => $result['branch'],
            'user'    => $result['user'],
            'token'   => $result['token'],
        ], 201);
    }
}


class OrganizationController extends Controller
{
    public function __construct(private GroupRegistrationService $service) {}

    /**
     * GET /api/v1/organization/branches
     * Lists all branches for the authenticated GROUP_ADMIN's organization.
     */
    public function branches(Request $request)
    {
        $user = Auth::user();

        if ($user->is_super_admin) {
            $branches = Hotel::with('group')->get();
        } elseif ($user->isGroupAdmin()) {
            $branches = HotelGroup::find($user->hotel_group_id)
                ->branches()
                ->withCount(['rooms', 'users'])
                ->get();
        } else {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json(['data' => $branches]);
    }

    /**
     * POST /api/v1/organization/branches
     * Creates a new branch hotel within the authenticated admin's group.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'    => 'required|string|min:2|max:255',
            'email'   => 'nullable|email',
            'phone'   => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        if (!$user->isGroupAdmin() && !$user->is_super_admin) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $groupId = $user->hotel_group_id ?? $request->input('group_id');
        $group = HotelGroup::findOrFail($groupId);

        $branch = $this->service->createBranch($group, $validated);

        return response()->json([
            'message' => 'Branch created successfully.',
            'branch'  => $branch,
        ], 201);
    }

    /**
     * GET /api/v1/organization/overview
     * Returns group-level summary stats.
     */
    public function overview(Request $request)
    {
        $user = Auth::user();

        if ($user->is_super_admin) {
            $group = null;
            $branchCount = Hotel::count();
        } elseif ($user->isGroupAdmin()) {
            $group = HotelGroup::find($user->hotel_group_id);
            $branchCount = $group?->branches()->count() ?? 0;
        } else {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'group'        => $group,
            'branch_count' => $branchCount,
        ]);
    }
}

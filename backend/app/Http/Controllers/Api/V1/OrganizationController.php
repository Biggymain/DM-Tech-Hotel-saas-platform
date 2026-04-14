<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\HotelGroup;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Services\GroupRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'tier'    => 'nullable|string|in:basic,standard,premium,enterprise',
        ]);

        if (!$user->isGroupAdmin() && !$user->is_super_admin) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $groupId = $user->hotel_group_id ?? $request->input('group_id');
        $group = HotelGroup::findOrFail($groupId);

        $validated['name'] = strip_tags($validated['name']);
        $validated['address'] = isset($validated['address']) ? strip_tags($validated['address']) : null;
        $validated['phone'] = isset($validated['phone']) ? strip_tags($validated['phone']) : null;

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
        } elseif ($user->hotel_group_id) {
            // Both Group Admins and Branch Staff have hotel_group_id
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

    /**
     * PUT /api/v1/organization/settings
     * Updates group-level settings (branding, currency).
     */
    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        if (!$user->is_super_admin && !$user->isGroupAdmin()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name'          => 'nullable|string|max:255',
            'currency'      => 'nullable|string|size:3',
            'tax_rate'      => 'nullable|numeric|min:0|max:100',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $groupId = $user->hotel_group_id;
        if ($user->is_super_admin && $request->has('group_id')) {
            $groupId = $request->input('group_id');
        }

        if (!$groupId) {
            return response()->json(['error' => 'No group context found.'], 400);
        }

        $group = HotelGroup::findOrFail($groupId);
        if (isset($validated['name'])) {
            $validated['name'] = strip_tags($validated['name']);
        }
        $group->update($validated);

        return response()->json([
            'message' => 'Organization settings updated successfully.',
            'group'   => $group,
        ]);
    }

    /**
     * POST /api/v1/organization/branches/{id}/onboard
     * Onboards a manager for the specified branch.
     */
    public function onboardManager(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->isGroupAdmin() && !$user->is_super_admin) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $branch = Hotel::findOrFail($id);
        
        // Find or create a manager for this branch
        $managerRole = Role::where('slug', 'manager')->where('hotel_id', $branch->id)->first();
        if (!$managerRole) {
            // If no branch-specific manager role, look for system manager role
            $managerRole = Role::where('slug', 'manager')->whereNull('hotel_id')->first();
        }

        if (!$managerRole) {
            return response()->json(['error' => 'Manager role not found.'], 404);
        }

        $manager = User::where('hotel_id', $branch->id)
            ->whereHas('roles', function($q) use ($managerRole) {
                $q->where('roles.id', $managerRole->id);
            })->first();

        $generatedPassword = null;
        if (!$manager) {
            $generatedPassword = Str::random(10);
            $manager = User::create([
                'hotel_id' => $branch->id,
                'name' => 'Branch Manager - ' . $branch->name,
                'email' => 'manager.' . Str::slug($branch->name) . '@hotel.com',
                'password' => $generatedPassword,
                'is_active' => true,
            ]);
            $manager->roles()->attach($managerRole->id, ['hotel_id' => $branch->id]);
        }

        return response()->json([
            'message' => 'Manager onboarded successfully.',
            'manager_email' => $manager->email,
            'temporary_password' => $generatedPassword,
            'branch_slug' => $branch->slug,
        ]);
    }
}

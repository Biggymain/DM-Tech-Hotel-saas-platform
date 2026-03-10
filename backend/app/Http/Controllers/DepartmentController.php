<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\PermissionService;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $departments = Department::where('hotel_id', $hotelId)->with('outlet')->get();
        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $hotelId = $request->user()->hotel_id;
        $slug = Str::slug($validated['name']);

        // Check if department strictly exists with this slug in this hotel
        if (Department::where('hotel_id', $hotelId)->where('slug', $slug)->exists()) {
             return response()->json(['message' => 'Department already exists.'], 422);
        }

        return DB::transaction(function () use ($validated, $hotelId, $slug, $request) {
            // 1. Create Department
            $department = Department::create([
                'hotel_id' => $hotelId,
                'outlet_id' => $validated['outlet_id'],
                'name' => $validated['name'],
                'slug' => $slug,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // 2. Map Permissions based on department name/type
            $permissionsToAssign = $this->getDepartmentPermissions($department->name);
            
            if (!empty($permissionsToAssign)) {
                $permissionIdsToAttach = [];
                foreach ($permissionsToAssign as $perm) {
                    $permission = Permission::firstOrCreate(
                        ['hotel_id' => null, 'slug' => $perm['slug']],
                        ['name' => $perm['name'], 'module' => 'departments']
                    );
                    
                    // Tenant isolation requires attaching with the tenant's hotel_id
                    $permissionIdsToAttach[$permission->id] = ['hotel_id' => $hotelId];
                }

                $department->permissions()->syncWithoutDetaching($permissionIdsToAttach);
            }

            return response()->json([
                'message' => 'Department created successfully.',
                'department' => $department
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'outlet_id' => 'exists:outlets,id',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully.',
            'department' => $department
        ]);
    }

    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    protected function getDepartmentPermissions(string $departmentName): array
    {
        $name = strtolower($departmentName);
        
        if (str_contains($name, 'kitchen')) {
            return [
                ['name' => 'View Orders', 'slug' => 'orders.view'],
                ['name' => 'Update Order Status', 'slug' => 'orders.status.update'],
                ['name' => 'Mark Item Prepared', 'slug' => 'orders.items.prepared'],
            ];
        }

        if (str_contains($name, 'pos') || str_contains($name, 'restaurant') || str_contains($name, 'bar')) {
            return [
                ['name' => 'Create Order', 'slug' => 'orders.create'],
                ['name' => 'Update Order', 'slug' => 'orders.update'],
                ['name' => 'Close Order', 'slug' => 'orders.close'],
            ];
        }

        if (str_contains($name, 'reception') || str_contains($name, 'front desk')) {
            return [
                ['name' => 'Create Reservation', 'slug' => 'reservations.create'],
                ['name' => 'Check In Guest', 'slug' => 'guests.check_in'],
                ['name' => 'Check Out Guest', 'slug' => 'guests.check_out'],
            ];
        }

        return [];
    }
}

# Role and Permission System Architecture

## Overview
The platform uses a custom role and permission system built on top of the existing Tenant Isolation model (`hotel_id`). The system ensures complete isolation between hotels while providing fine-grained access control mapped directly to API routes via middleware.

## Core Components

### 1. Database Architecture
- **`roles`**: Contains hotel roles (`name`, `slug`, `hotel_id`, `is_system_role`). System roles have a `null` `hotel_id`.
- **`permissions`**: Contains granular actions (`name`, `slug`, `module`, `hotel_id`).
- **`role_permissions`**: Maps permissions to roles (many-to-many) via a pivot table containing `hotel_id`.
- **`user_roles`**: Maps users to roles (many-to-many) via a pivot table containing `hotel_id`.

### 2. Services & Middleware
- **`PermissionService`**: Responsible for determining if a given user has a requested permission. Checks if the user has `is_super_admin` first. If not, it fetches the user's roles and checks their attached permissions. Results are heavily cached automatically to prevent redundant database hits.
- **`RoleVerificationMiddleware`**: Aliased to `role.verify`. Applied directly on `routes/api.php` to intercept requests, pass the required permission slug to the `PermissionService`, and throw a custom 403 `{"status": "error", "message": "Permission denied"}` JSON response if authorization fails.

## Usage Guide

### Defining Protected Routes
To secure an API endpoint, attach the `role.verify:{permissionSlug}` middleware to the route definition.
```php
Route::prefix('rooms')
     ->middleware('role.verify:rooms.manage')
     ->group(function() {
    // Controller actions here...
});
```

### Assigning Roles to Users
Because a User can belong to multiple roles within a hotel, the relationship uses `belongsToMany`.
```php
$user = User::find(1);
$role = Role::where('slug', 'manager')->first();

// Attach a role to a user
$user->roles()->attach($role->id, ['hotel_id' => $user->hotel_id]);
```

### Creating Custom Hotel Roles
Hotels can create their custom roles mapped to specific permissions.
```php
$customRole = Role::create([
    'hotel_id' => $activeHotelId,
    'name' => 'Custom Shift Manager',
    'slug' => 'custom-shift-manager',
    'is_system_role' => false
]);

// Attach permissions to the custom role
$customRole->permissions()->attach([$permissionId1, $permissionId2], ['hotel_id' => $activeHotelId]);
```

### Global Bypass
Any user with `is_super_admin = true` will automatically pass all `role.verify` middleware checks without needing explicit role assignments. This is safely managed inside `PermissionService`.

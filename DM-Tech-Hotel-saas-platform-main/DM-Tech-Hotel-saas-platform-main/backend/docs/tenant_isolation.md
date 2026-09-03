# Tenant Isolation Architecture

## Overview
This platform is a multi-tenant SaaS application where data for individual hotels (tenants) is strictly isolated. The isolation ensures that users from Hotel A cannot access or modify data belonging to Hotel B, while allowing a global `SuperAdmin` role to manage all data across the platform.

## Key Components

1. **`Tenantable` Trait (`App\Traits\Tenantable`)**
   - Applies the `TenantScope` global query scope to any model using it.
   - Automatically assigns the authenticated user's `hotel_id` to newly created records (unless the user is a `SuperAdmin` or the model is `Hotel` itself).

2. **`TenantScope` (`App\Models\Scopes\TenantScope`)**
   - Automatically appends a `WHERE hotel_id = ?` clause to all `SELECT`, `UPDATE`, and `DELETE` queries.
   - The scope is bypassed if the authenticated user has the `is_super_admin` flag.

3. **`TenantIsolationMiddleware` (`App\Http\Middleware\TenantIsolationMiddleware`)**
   - Enforces that any authenticated non-SuperAdmin user requesting the API belongs to a tenant (i.e., has a non-null `hotel_id`).
   - If they do not belong to a tenant, they are rejected with a `403 Unauthorized` response.
   - Optionally binds the `tenant_id` string into the Laravel Service Container using `app()->instance('tenant_id', $user->hotel_id)`.
   - Registered globally for all API routes in `bootstrap/app.php` alongside `ForceJsonResponse`.

## How to Make New Models Tenant-Aware

Whenever you create a new module (e.g., `Room`, `Reservation`, `Invoice`), follow these steps to ensure its data is securely isolated:

1. **Database Migration**
   Add a `hotel_id` foreign key column to the table:
   ```php
   $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
   ```

2. **Eloquent Model**
   Import and apply the `Tenantable` trait:
   ```php
   use Illuminate\Database\Eloquent\Model;
   use App\Traits\Tenantable;

   class ExampleModel extends Model
   {
       use Tenantable;
       // ...
   }
   ```

By doing this, any queries like `ExampleModel::all()` will automatically be scoped to the active hotel. If you insert records `ExampleModel::create(...)`, the `hotel_id` will be seamlessly attached based on the logged-in user.

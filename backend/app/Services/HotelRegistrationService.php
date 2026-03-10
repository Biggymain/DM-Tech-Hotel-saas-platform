<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class HotelRegistrationService
{
    /**
     * Register a new hotel tenant and its owner.
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function registerHotel(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Create the Hotel Tenant
            $hotel = Hotel::create([
                'name' => $data['hotel_name'],
                'domain' => \Illuminate\Support\Str::slug($data['hotel_name']),
                'is_active' => true,
            ]);

            // 2. Create the Owner User
            $owner = User::create([
                'hotel_id' => $hotel->id,
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_super_admin' => false,
            ]);

            // 3. Assign HotelOwner role
            $ownerRole = Role::where('slug', 'hotelowner')->first();
            if ($ownerRole) {
                // Attach the role with the hotel_id pivot
                $owner->roles()->attach($ownerRole->id, ['hotel_id' => $hotel->id]);
            }

            // 4. Generate Default Outlets
            $this->generateDefaultOutlets($hotel->id);

            // 5. Generate Default Settings
            $this->generateDefaultSettings($hotel->id);

            // 6. Generate Token
            $token = $owner->createToken('auth_token')->plainTextToken;

            return [
                'hotel' => $hotel,
                'user' => $owner,
                'token' => $token
            ];
        });
    }

    /**
     * Generate default outlets for a new hotel.
     *
     * @param int $hotelId
     * @return void
     */
    protected function generateDefaultOutlets(int $hotelId): void
    {
        $defaultOutlets = [
            ['name' => 'Main Restaurant', 'type' => 'restaurant'],
            ['name' => 'Room Service', 'type' => 'room_service'],
            ['name' => 'Main Bar', 'type' => 'bar'],
        ];

        foreach ($defaultOutlets as $outletData) {
            Outlet::create([
                'hotel_id' => $hotelId,
                'name' => $outletData['name'],
                'type' => $outletData['type'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Generate default settings for a new hotel.
     *
     * @param int $hotelId
     * @return void
     */
    protected function generateDefaultSettings(int $hotelId): void
    {
        $defaultSettings = [
            ['setting_key' => 'currency', 'setting_value' => 'USD', 'type' => 'string'],
            ['setting_key' => 'timezone', 'setting_value' => 'UTC', 'type' => 'string'],
            ['setting_key' => 'tax_rate', 'setting_value' => '0', 'type' => 'string'],
        ];

        foreach ($defaultSettings as $setting) {
            \App\Models\HotelSetting::create([
                'hotel_id' => $hotelId,
                'setting_key' => $setting['setting_key'],
                'setting_value' => $setting['setting_value'],
                'type' => $setting['type'],
            ]);
        }
    }
}

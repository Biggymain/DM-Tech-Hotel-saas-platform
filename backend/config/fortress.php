<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Digital Fortress Configuration
    |--------------------------------------------------------------------------
    |
    | This file manages the security settings for the DM-Tech SaaS platform
    | including hardware marriage and system-level passphrases.
    |
    */

    /**
     * The master passphrase (DEV_PASSPHRASE) required for hardware marriage
     * and vault sealing for support staff.
     */
    'dev_passphrase' => env('DEV_PASSPHRASE'),

    /**
     * Argon2id hash of the developer passphrase for Master terminal registration.
     */
    'dev_passphrase_hash' => env('DEV_PASSPHRASE_HASH'),

    /**
     * Secret key for developer-level Supabase operations.
     */
    'supabase_dev_key' => env('X_SUPABASE_DEV_KEY'),

    /**
     * Role-to-Port mapping for the 6-port architecture.
     * Used for strict SIEM-ready port enforcement.
     */
    'port_mapping' => [
        'superadmin'      => 3000,
        'groupadmin'      => 3000,
        'hotelowner'      => 3002,
        'generalmanager'  => 3002,
        'manager'         => 3002,
        'receptionist'    => 3002,
        'reception'       => 3002,
        'supportstaff'    => 3003,
        'kitchenmanager'  => 3003,
        'outletmanager'   => 3003,
        'hotelstaff'      => 3003,
        'staff'           => 3003,
        'waiter'          => 3003,
        'steward'         => 3003,
        'bartender'       => 3003,
        'chef'            => 3003,
        'kitchen'         => 3003,
        'housekeeper'     => 3003,
        'housekeeping'    => 3003,
        'waitress'        => 3003,
        'cashier'         => 3003,
        'itspecialist'    => 3000,
        'financeadmin'    => 3005,
        'accountant'      => 3005,
        'auditor'         => 3005,
        'biadmin'         => 3005,
        'master_admin'    => 3000,
        'terminal'        => 3003,
        'guest'           => 3004,
    ],

];

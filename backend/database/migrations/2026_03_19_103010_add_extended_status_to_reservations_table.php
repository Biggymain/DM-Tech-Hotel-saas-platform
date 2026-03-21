<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Drop the existing check constraint
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');

            // Add the updated check constraint including 'extended'
            DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'confirmed'::text, 'checked_in'::text, 'checked_out'::text, 'cancelled'::text, 'no_show'::text, 'extended'::text]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');
            DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'confirmed'::text, 'checked_in'::text, 'checked_out'::text, 'cancelled'::text, 'no_show'::text]))");
        }
    }
};

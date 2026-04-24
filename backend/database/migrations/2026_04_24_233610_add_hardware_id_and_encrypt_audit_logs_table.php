<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('hardware_id')->nullable()->index()->after('user_id');
        });

        // Zero-Data-Loss Transformation
        $logs = DB::table('audit_logs')->get();

        foreach ($logs as $log) {
            $newValues = json_decode($log->new_values, true);
            $hardwareId = null;

            if (is_array($newValues)) {
                $hardwareId = $newValues['attempted'] ?? ($newValues['hash'] ?? null);
            }

            DB::table('audit_logs')->where('id', $log->id)->update([
                'hardware_id' => $hardwareId,
                'reason' => $log->reason ? Crypt::encryptString($log->reason) : null,
                'old_values' => $log->old_values ? Crypt::encryptString($log->old_values) : null,
                'new_values' => $log->new_values ? Crypt::encryptString($log->new_values) : null,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To prevent accidental data loss of encrypted fields, we only drop the column
        // A full decryption rollback would be unsafe if down() is called accidentally in prod.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('hardware_id');
        });
    }
};

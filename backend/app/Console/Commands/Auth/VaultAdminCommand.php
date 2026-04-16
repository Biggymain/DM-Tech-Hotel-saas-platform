<?php

namespace App\Console\Commands\Auth;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SecurityKeyMismatchException;
use App\Services\HardwareFingerprintService;
use Illuminate\Support\Str;

class VaultAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fortress:vault-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'De-identify local software by migrating Super Admin credentials to the Supabase Vault.';

    /**
     * Execute the console command.
     */
    public function handle(HardwareFingerprintService $fingerprintService)
    {
        $this->info('Starting Digital Fortress: Vault Admin Handshake...');

        $adminEmail = config('app.super_admin_email');
        $plainPass = config('app.super_admin_password');
        $passphrase = config('fortress.dev_passphrase');
        $hardwareHash = $fingerprintService->generateHash();

        if (empty($adminEmail) || empty($plainPass)) {
            $this->error('SUPER_ADMIN_EMAIL or SUPER_ADMIN_PASSWORD missing from .env');
            return 1;
        }

        if (empty($passphrase)) {
            $this->error('DEV_PASSPHRASE is missing. Digital Fortress cannot proceed.');
            return 1;
        }

        $this->warn("Identifying User: {$adminEmail}");
        $this->info("Hardware ID: {$hardwareHash}");

        try {
            // 0. Pre-Flight Handshake Test
            $this->info('Running Pre-Flight Handshake Test...');
            $testString = 'FORTRESS_TEST_VAL_' . Str::random(8);
            
            $testEncrypted = DB::connection('supabase')->selectOne(
                "SELECT encrypt_sensitive_data(?, ?) as encrypted",
                [$testString, $passphrase]
            )->encrypted;

            if (!$testEncrypted) {
                throw new \Exception('Pre-flight encryption test failed (RPC returned null).');
            }

            $testDecrypted = DB::connection('supabase')->selectOne(
                "SELECT decrypt_sensitive_data(?, ?) as plain_text",
                [$testEncrypted, $passphrase]
            )->plain_text;

            if ($testDecrypted !== $testString) {
                throw new \Exception('Pre-flight decryption test failed (Mismatched result).');
            }

            $this->info('✅ Pre-flight Handshake: SUCCESS');

            // 1. Manually encrypt via Supabase RPC
            $this->info('Encrypting credentials via hardware-locked RPC...');
            
            $encryptedPass = DB::connection('supabase')->selectOne(
                "SELECT encrypt_sensitive_data(?, ?) as encrypted",
                [$plainPass, $passphrase]
            )->encrypted;

            if (!$encryptedPass) {
                $this->error('!!! SYSTEM LOCKDOWN !!!');
                $this->error('Encryption failed: Supabase RPC returned empty result.');
                return 1;
            }

            // 2. Update or Insert into the Supabase users table
            $this->info("Storing 'De-identified' Identity in Supabase Vault...");
            
            DB::connection('supabase')->table('users')->updateOrInsert(
                ['email' => $adminEmail],
                [
                    'name' => 'Platform Super Admin',
                    'password' => $encryptedPass, 
                    'is_super_admin' => true,
                    'is_on_duty' => true,
                    'must_change_password' => false,
                    'hardware_hash' => $hardwareHash, // Married to this hardware
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // 3. The Software Lock / Handshake verification
            $this->info('Verifying Local-to-Vault Handshake...');

            // Fetch through the User model (with local-to-cloud cast)
            $user = User::on('supabase')->where('email', $adminEmail)->first();

            if (!$user) {
                throw new \Exception('Handshake failed: Created user not found in Supabase.');
            }

            // Verify Hardware Marriage
            if ($user->hardware_hash !== $hardwareHash) {
                $this->error('!!! SYSTEM LOCKDOWN !!!');
                $this->error('Hardware Marriage Failed: Handshake mismatch detected.');
                return 1;
            }

            // Accessing the password attribute triggers Decryption
            $decryptedPass = $user->password;

            if ($decryptedPass !== $plainPass) {
                $this->error('!!! SYSTEM LOCKDOWN !!!');
                $this->error('Decryption Handshake Failed: Password mismatch.');
                return 1;
            }

            $this->info('----------------------------------------------------');
            $this->info('🔒 DIGITAL FORTRESS HANDSHAKE SUCCESSFUL');
            $this->info('----------------------------------------------------');
            $this->info('The software has successfully de-identified the Super Admin.');
            $this->info('Hardware Marriage: CONFIRMED');
            $this->info('Cloud Vault Verification: CONFIRMED');
            $this->info('');
            
            if ($this->confirm('Handshake successful. Do you want to finalize de-identification by removing credentials from .env?', true)) {
                $this->warn('ACTION REQUIRED: Please manually delete SUPER_ADMIN_PASSWORD from your .env file now.');
                $this->info('The software is now secured by the Digital Fortress.');
            }

        } catch (SecurityKeyMismatchException $e) {
            $this->error('!!! SYSTEM LOCKDOWN !!!');
            $this->error('Security Key Handshake Failed: ' . $e->getMessage());
            Log::critical('Digital Fortress Handshake Failed: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('System Error: ' . $e->getMessage());
            Log::error('Vault Admin Command Failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

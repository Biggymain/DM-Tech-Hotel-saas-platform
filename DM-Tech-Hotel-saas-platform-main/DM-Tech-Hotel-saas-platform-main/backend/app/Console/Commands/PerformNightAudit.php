<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Services\FolioService;
use Carbon\Carbon;

class PerformNightAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pms:night-audit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform the night audit: post daily room charges to active guest folios.';

    protected $folioService;

    public function __construct(FolioService $folioService)
    {
        parent::__construct();
        $this->folioService = $folioService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Night Audit: ' . now()->toDateTimeString());

        $reservations = Reservation::where('status', 'checked_in')->get();
        $today = Carbon::today();

        foreach ($reservations as $reservation) {
            $this->processReservation($reservation, $today);
        }

        $this->info('Night Audit completed.');
    }

    /**
     * Process a single reservation for night audit.
     */
    protected function processReservation(Reservation $reservation, Carbon $today)
    {
        $primaryFolio = $reservation->folios()->where('status', 'open')->first();

        if (!$primaryFolio) {
            $this->warn("No open folio found for reservation #{$reservation->reservation_number}");
            return;
        }

        // Double-post protection
        $alreadyCharged = $primaryFolio->items()
            ->where('description', 'Room Charge')
            ->whereDate('created_at', $today)
            ->exists();

        if ($alreadyCharged) {
            $this->line("Skipping reservation #{$reservation->reservation_number}: Room charge already posted for today.");
            return;
        }

        // Calculate charge (sum of rates for all rooms in reservation)
        $dailyRate = $reservation->rooms->sum('pivot.rate');

        if ($dailyRate <= 0) {
            $this->warn("Skipping reservation #{$reservation->reservation_number}: Daily rate is 0.");
            return;
        }

        try {
            $this->folioService->addCharge(
                $primaryFolio,
                'Room Charge',
                $dailyRate,
                null,
                null,
                'ROOM'
            );
            $this->info("Posted Room Charge of ₦" . number_format($dailyRate, 2) . " to reservation #{$reservation->reservation_number}");
        } catch (\Exception $e) {
            $this->error("Failed to post charge for reservation #{$reservation->reservation_number}: " . $e->getMessage());
        }
    }
}

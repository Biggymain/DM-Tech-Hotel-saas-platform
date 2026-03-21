<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Services\ReportingService;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected ReportingService $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    private function getDateRange(Request $request): array
    {
        $start = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $end = $request->query('end_date', Carbon::now()->toDateString());
        return [$start, $end];
    }

    public function dashboard(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $data = $this->reportingService->getDashboardSummary($hotelId);
        
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function dailySales(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        list($start, $end) = $this->getDateRange($request);
        
        $data = $this->reportingService->getDailySales($hotelId, $start, $end);
        
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function outletPerformance(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        list($start, $end) = $this->getDateRange($request);
        
        $data = $this->reportingService->getOutletPerformance($hotelId, $start, $end);
        
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function menuPerformance(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        list($start, $end) = $this->getDateRange($request);
        
        $data = $this->reportingService->getMenuPerformance($hotelId, $start, $end);
        
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function paymentBreakdown(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        list($start, $end) = $this->getDateRange($request);
        
        $data = $this->reportingService->getPaymentBreakdown($hotelId, $start, $end);
        
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function inventoryUsage(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        list($start, $end) = $this->getDateRange($request);
        
        $data = $this->reportingService->getInventoryUsage($hotelId, $start, $end);
        
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function exportDailySales(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        list($start, $end) = $this->getDateRange($request);
        $data = $this->reportingService->getDailySales($hotelId, $start, $end);
        
        return $this->streamCsv('daily_sales.csv', $data, ['Date', 'Total Revenue', 'Total Tax', 'Service Charge', 'Total Invoices']);
    }

    public function exportOutletPerformance(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        list($start, $end) = $this->getDateRange($request);
        $data = $this->reportingService->getOutletPerformance($hotelId, $start, $end);
        
        return $this->streamCsv('outlet_performance.csv', $data, ['Outlet Name', 'Total Revenue', 'Total Orders']);
    }

    private function streamCsv(string $filename, array $data, array $headers)
    {
        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            foreach ($data as $row) {
                $rowData = is_object($row) ? (array)$row : $row;
                fputcsv($file, array_values($rowData));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}"
        ]);
    }
}

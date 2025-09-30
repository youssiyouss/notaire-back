<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Models\Client;
use App\Models\Company;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Time filter (day, month, year)
        $time = $request->get('time', 'year');
        $year = $request->get('year', Carbon::now()->year);
        $month = $request->get('month'); // peut être null

        $dateRange = $this->getDateRange($time, $year, $month);
        Log::info($dateRange);

        // === 1. Total Income ===
        $totalIncome = Contract::where('status', 'Payé')
            ->whereBetween('created_at', $dateRange)
            ->sum('price');

            // === 2. Contracts this month (peak registration) ===
        $contractsThisMonth = Contract::whereBetween('created_at', [Carbon::now()->startOfMonth() ,Carbon::now()->endOfMonth()])
            ->count();

        // === 2. Contracts per month (peak registration) ===
        $contractsPerMonth = Contract::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->whereBetween('created_at', $dateRange)
            ->groupBy('month')
            ->pluck('total', 'month');
        Log::info($contractsPerMonth);

        // === 3. Unpaid Contracts ===
        $unpaidContracts = Contract::where('status', 'Non Payé')
            ->whereBetween('created_at', $dateRange)
            ->count();

        // === 4. Clients per state (basic, parsing last part of address) ===
        $clientsPerState = DB::table('users')
            ->selectRaw('SUBSTRING_INDEX(adresse, ",", -1) as state, COUNT(*) as total')
            ->where('role','Client')
            ->groupBy('state')
            ->get();

        // === 5. Clients vs Companies ===
        $clientsCount = DB::table('clients')->count();
        $companiesCount = DB::table('companies')->count();

        // === 6. Tasks stats ===
        $tasksStats = Task::select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', $dateRange)
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');


        return response()->json([
            'total_income'        => $totalIncome,
            'contracts_per_month' => $contractsPerMonth,
            'contracts_this_month' => $contractsThisMonth,
            'unpaid_contracts'    => $unpaidContracts,
            'clients_per_state'   => $clientsPerState,
            'clients'   => $clientsCount,
            'companies' => $companiesCount,
            'tasks' => $tasksStats,
        ]);
    }

    private function getDateRange($time, $year = null, $month = null)
    {
        $now = Carbon::now();

        switch ($time) {
            case 'day':
                if ($year && $month && request()->get('day')) {
                    $day = request()->get('day');
                    $date = Carbon::createFromDate($year, $month, $day);
                    return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
                }
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

            case 'month':
                if ($year && $month) {
                    $date = Carbon::createFromDate($year, $month, 1);
                    return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
                }
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];

            case 'year':
            default:
                if ($year) {
                    $date = Carbon::createFromDate($year, 1, 1);
                    return [$date->copy()->startOfYear(), $date->copy()->endOfYear()];
                }
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
        }
    }


}

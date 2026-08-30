<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalCompanies = Company::count();
        $activeUsers = User::where('is_active', 1)->count();

        $period = $request->query('period', 30); // Default to 30 days
        $startDate = now()->subDays($period)->startOfDay();

        // Get companies created within the period
        $companies = Company::where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'asc')
            ->get();

        $chartData = [];

        if ($period == 30) {
            // Group by Day
            $grouped = $companies->groupBy(function($date) {
                return \Carbon\Carbon::parse($date->created_at)->format('Y-m-d');
            });

            for ($i = $period; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $chartData[] = [
                    'label' => $date,
                    'count' => isset($grouped[$date]) ? $grouped[$date]->count() : 0,
                ];
            }
        } else {
            // Group by Month (3 or 6 months)
            $months = $period == 90 ? 3 : 6;
            $startDate = now()->subMonths($months)->startOfMonth();
            
            $companies = Company::where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'asc')
                ->get();

            $grouped = $companies->groupBy(function($date) {
                return \Carbon\Carbon::parse($date->created_at)->format('Y-m'); // e.g. 2026-08
            });

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i)->format('Y-m');
                $chartData[] = [
                    'label' => $date,
                    'count' => isset($grouped[$date]) ? $grouped[$date]->count() : 0,
                ];
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'total_companies' => $totalCompanies,
                'active_users' => $activeUsers,
                'chart_data' => $chartData,
            ]
        ]);
    }
}

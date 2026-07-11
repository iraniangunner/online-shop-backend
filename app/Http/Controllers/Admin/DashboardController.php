<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $startDate = Carbon::today()->subDays(29); // ۳۰ روز اخیر شامل امروز

        // ---------- شمارش وضعیت نوبت‌ها ----------
        $statusCounts = Appointment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalAppointments = $statusCounts->sum();

        // ---------- درآمد کل (فقط پرداخت‌های موفق) ----------
        $totalRevenue = Payment::where('status', Payment::STATUS_PAID)->sum('amount');

        // ---------- تعداد کاربران و متخصص‌ها ----------
        $totalCustomers = User::where('role', User::ROLE_CUSTOMER)->count();
        $totalSpecialists = Specialist::where('is_active', true)->count();
        $pendingRefundsCount = Payment::where('status', Payment::STATUS_REFUND_PENDING)->count();

        // ---------- نوبت‌های ۳۰ روز اخیر (برای نمودار خطی) ----------
        $appointmentsByDay = Appointment::where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $appointmentsChart = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $appointmentsChart[] = [
                'date' => $date,
                'count' => (int) ($appointmentsByDay[$date] ?? 0),
            ];
        }

        // ---------- درآمد ۳۰ روز اخیر (برای نمودار خطی) ----------
        $revenueByDay = Payment::where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $startDate)
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('sum(amount) as total'))
            ->groupBy('date')
            ->pluck('total', 'date');

        $revenueChart = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $revenueChart[] = [
                'date' => $date,
                'amount' => (int) ($revenueByDay[$date] ?? 0),
            ];
        }

        // ---------- پرفروش‌ترین خدمات (۵ تای اول) ----------
        $topServices = Service::select('services.id', 'services.name')
            ->selectRaw('count(appointment_service.appointment_id) as bookings_count')
            ->join('appointment_service', 'appointment_service.service_id', '=', 'services.id')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => [
                'total_appointments' => $totalAppointments,
                'confirmed' => (int) ($statusCounts['confirmed'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
                'pending_payment' => (int) ($statusCounts['pending_payment'] ?? 0),
                'no_show' => (int) ($statusCounts['no_show'] ?? 0),
                'total_revenue' => (int) $totalRevenue,
                'total_customers' => $totalCustomers,
                'total_specialists' => $totalSpecialists,
                'pending_refunds_count' => $pendingRefundsCount,
            ],
            'appointments_chart' => $appointmentsChart,
            'revenue_chart' => $revenueChart,
            'top_services' => $topServices,
        ]);
    }
}

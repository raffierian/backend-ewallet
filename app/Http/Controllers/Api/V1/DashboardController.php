<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'period' => 'sometimes|in:today,week,month,year',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        $family = Family::findOrFail($request->family_id);

        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $period = $request->period ?? 'month';
        [$dateFrom, $dateTo] = $this->getDateRange($period, $request->date_from, $request->date_to);

        // Total balance across all active accounts
        $totalBalance = (float) $family->accounts()
            ->where('is_active', true)
            ->sum('balance');

        // All-time savings accumulated (saving - withdraw_saving)
        $totalSavingsDeposits = (float) Transaction::where('family_id', $family->id)
            ->where('type', 'saving')
            ->sum('amount');
        $totalSavingsWithdrawals = (float) Transaction::where('family_id', $family->id)
            ->where('type', 'withdraw_saving')
            ->sum('amount');
        $totalSavings = max(0, $totalSavingsDeposits - $totalSavingsWithdrawals);

        // Income and expense for the period
        $income = (float) Transaction::where('family_id', $family->id)
            ->where('type', 'income')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('amount');

        $expense = (float) Transaction::where('family_id', $family->id)
            ->where('type', 'expense')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('amount');

        // Period savings (saving - withdraw_saving)
        $periodSavingDeposits = (float) Transaction::where('family_id', $family->id)
            ->where('type', 'saving')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('amount');

        $periodSavingWithdrawals = (float) Transaction::where('family_id', $family->id)
            ->where('type', 'withdraw_saving')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('amount');

        $netSaving = $periodSavingDeposits - $periodSavingWithdrawals;
        $netCashflow = $income - $expense - $netSaving;

        // Top 5 expense categories
        $topExpenseCategories = Transaction::where('family_id', $family->id)
            ->where('type', 'expense')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Top 3 highest expenses
        $topExpenses = Transaction::where('family_id', $family->id)
            ->where('type', 'expense')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->with(['user', 'account', 'category'])
            ->orderByDesc('amount')
            ->limit(3)
            ->get();

        // Recent transactions
        $recentTransactions = Transaction::where('family_id', $family->id)
            ->with(['user', 'account', 'category'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Daily trend for the last 7 days
        $sevenDaysAgo = now()->subDays(6)->toDateString();
        $today = now()->toDateString();

        $dailyTrend = Transaction::where('family_id', $family->id)
            ->whereBetween('date', [$sevenDaysAgo, $today])
            ->select(
                'date',
                'type',
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get()
            ->groupBy(function ($item) {
                return $item->date instanceof \Carbon\CarbonInterface
                    ? $item->date->format('Y-m-d')
                    : (string) $item->date;
            })
            ->map(function ($transactions, $date) {
                return [
                    'date' => (string) $date,
                    'income' => (float) $transactions->where('type', 'income')->sum('total'),
                    'expense' => (float) $transactions->where('type', 'expense')->sum('total'),
                    'saving' => (float) ($transactions->where('type', 'saving')->sum('total') - $transactions->where('type', 'withdraw_saving')->sum('total')),
                ];
            })
            ->values();

        // Budget progress
        $budgets = $family->budgets()
            ->where('is_active', true)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->with('category')
            ->get()
            ->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'category' => $budget->category,
                    'amount' => $budget->amount,
                    'spent' => $budget->spent,
                    'progress' => $budget->progress,
                    'remaining' => $budget->amount - $budget->spent,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_balance' => $totalBalance,
                'total_savings' => $totalSavings,
                'net_worth' => $totalBalance + $totalSavings,
                'period_income' => $income,
                'period_expense' => $expense,
                'period_saving' => $netSaving,
                'period_saving_deposit' => $periodSavingDeposits,
                'period_saving_withdrawal' => $periodSavingWithdrawals,
                'net_cashflow' => $netCashflow,
                'top_expense_categories' => $topExpenseCategories,
                'top_expenses' => $topExpenses,
                'recent_transactions' => $recentTransactions,
                'daily_trend' => $dailyTrend,
                'budgets' => $budgets,
            ],
        ]);
    }

    private function getDateRange(string $period, ?string $customFrom = null, ?string $customTo = null): array
    {
        if ($customFrom && $customTo) {
            return [
                \Illuminate\Support\Carbon::parse($customFrom)->toDateString(),
                \Illuminate\Support\Carbon::parse($customTo)->toDateString(),
            ];
        }

        return match($period) {
            'today' => [now()->toDateString(), now()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }
}

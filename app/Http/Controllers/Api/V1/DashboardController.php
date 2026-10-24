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
        [$dateFrom, $dateTo] = $this->getDateRange($period);

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
        $dailyTrend = Transaction::where('family_id', $family->id)
            ->whereBetween('date', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->select(
                'date',
                'type',
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($transactions, $date) {
                return [
                    'date' => $date,
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

    private function getDateRange($period): array
    {
        return match($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}

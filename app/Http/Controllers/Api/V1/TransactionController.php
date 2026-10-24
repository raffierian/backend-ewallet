<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Family;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'type' => 'sometimes|in:income,expense,transfer,saving,withdraw_saving',
            'category_id' => 'sometimes|exists:categories,id',
            'account_id' => 'sometimes|exists:accounts,id',
            'per_page' => 'sometimes|integer|min:1|max:500',
            'all' => 'sometimes|boolean',
        ]);

        $family = Family::findOrFail($request->family_id);

        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = Transaction::where('family_id', $request->family_id)
            ->with(['user', 'account', 'category', 'toAccount']);

        // Apply filters
        if ($request->filled('date_from')) {
            $query->where('date', '>=', \Illuminate\Support\Carbon::parse($request->date_from)->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', \Illuminate\Support\Carbon::parse($request->date_to)->toDateString());
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                  ->orWhereHas('category', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Calculate totals across the entire filtered query before pagination
        $summaryQuery = clone $query;
        $summarySums = $summaryQuery->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalIncome = (float) ($summarySums['income'] ?? 0);
        $totalExpense = (float) ($summarySums['expense'] ?? 0);
        $totalSavingDeposit = (float) ($summarySums['saving'] ?? 0);
        $totalSavingWithdraw = (float) ($summarySums['withdraw_saving'] ?? 0);
        $netSaving = $totalSavingDeposit - $totalSavingWithdraw;

        $summary = [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'total_saving' => $netSaving,
            'total_saving_deposit' => $totalSavingDeposit,
            'total_saving_withdrawal' => $totalSavingWithdraw,
            'net_cashflow' => $totalIncome - $totalExpense - $netSaving,
        ];

        if ($request->boolean('all') || $request->paginate === 'false') {
            $transactions = $query->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $transactions = $query->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);
        }

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $transactions,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'account_id' => 'required|exists:accounts,id',
            'type' => 'required|in:income,expense,transfer,saving,withdraw_saving',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'sometimes|date',
            'category_id' => 'nullable|required_if:type,income,expense|exists:categories,id',
            'note' => 'nullable|string',
            'receipt' => 'nullable|image|max:5120', // 5MB max
            'to_account_id' => 'nullable|required_if:type,transfer|exists:accounts,id',
        ]);

        $family = Family::findOrFail($request->family_id);
        $account = Account::findOrFail($request->account_id);

        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $data = [
                'family_id' => $request->family_id,
                'user_id' => $request->user()->id,
                'account_id' => $request->account_id,
                'category_id' => $request->category_id,
                'type' => $request->type,
                'amount' => $request->amount,
                'date' => $request->date ? \Illuminate\Support\Carbon::parse($request->date)->toDateString() : now()->toDateString(),
                'note' => $request->note,
                'to_account_id' => $request->to_account_id,
            ];

            // Handle receipt upload
            if ($request->hasFile('receipt')) {
                $path = $request->file('receipt')->store('receipts', 'public');
                $data['receipt_url'] = $path;
            }

            $transaction = Transaction::create($data);

            // Update account balance
            if ($request->type === 'income' || $request->type === 'withdraw_saving') {
                $account->increment('balance', $request->amount);
            } elseif ($request->type === 'expense' || $request->type === 'saving') {
                if ($account->balance < $request->amount) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                    ], 400);
                }
                $account->decrement('balance', $request->amount);
            } elseif ($request->type === 'transfer') {
                $toAccount = Account::findOrFail($request->to_account_id);
                
                if ($account->balance < $request->amount) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                    ], 400);
                }
                
                $account->decrement('balance', $request->amount);
                $toAccount->increment('balance', $request->amount);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => $transaction->load(['user', 'account', 'category', 'toAccount']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, Transaction $transaction)
    {
        // Check if user is member of the family
        if (!$transaction->family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction->load(['user', 'account', 'category', 'toAccount']),
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        // Check if user is member of the family
        if (!$transaction->family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'type' => 'sometimes|in:income,expense,transfer,saving,withdraw_saving',
            'account_id' => 'sometimes|exists:accounts,id',
            'to_account_id' => 'nullable|exists:accounts,id',
            'date' => 'sometimes|date',
            'category_id' => 'nullable|exists:categories,id',
            'note' => 'nullable|string',
            'receipt' => 'nullable|image|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $oldAmount = (float) $transaction->amount;
            $oldType = $transaction->type;
            $oldAccount = Account::findOrFail($transaction->account_id);
            $oldToAccount = $transaction->to_account_id ? Account::find($transaction->to_account_id) : null;

            // Revert old balance changes
            if ($oldType === 'income' || $oldType === 'withdraw_saving') {
                $oldAccount->decrement('balance', $oldAmount);
            } elseif ($oldType === 'expense' || $oldType === 'saving') {
                $oldAccount->increment('balance', $oldAmount);
            } elseif ($oldType === 'transfer' && $oldToAccount) {
                $oldAccount->increment('balance', $oldAmount);
                $oldToAccount->decrement('balance', $oldAmount);
            }

            // Update transaction
            $data = $request->only(['amount', 'type', 'account_id', 'to_account_id', 'date', 'category_id', 'note']);
            
            if (isset($data['date'])) {
                $data['date'] = \Illuminate\Support\Carbon::parse($data['date'])->toDateString();
            }

            if ($request->hasFile('receipt')) {
                $path = $request->file('receipt')->store('receipts', 'public');
                $data['receipt_url'] = $path;
            }

            $transaction->update($data);
            $transaction->refresh();

            // Reload fresh accounts for applying new changes
            $newAmount = (float) $transaction->amount;
            $newAccount = Account::findOrFail($transaction->account_id);

            // Apply new balance changes
            if ($transaction->type === 'income' || $transaction->type === 'withdraw_saving') {
                $newAccount->increment('balance', $newAmount);
            } elseif ($transaction->type === 'expense' || $transaction->type === 'saving') {
                if ($newAccount->balance < $newAmount) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                    ], 400);
                }
                $newAccount->decrement('balance', $newAmount);
            } elseif ($transaction->type === 'transfer') {
                $newToAccount = Account::findOrFail($transaction->to_account_id);
                if ($newAccount->balance < $newAmount) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                    ], 400);
                }
                $newAccount->decrement('balance', $newAmount);
                $newToAccount->increment('balance', $newAmount);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'data' => $transaction->fresh()->load(['user', 'account', 'category', 'toAccount']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        // Check if user is member of the family
        if (!$transaction->family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Revert balance changes
            if ($transaction->type === 'income' || $transaction->type === 'withdraw_saving') {
                $transaction->account->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense' || $transaction->type === 'saving') {
                $transaction->account->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer') {
                $transaction->account->increment('balance', $transaction->amount);
                if ($transaction->toAccount) {
                    $transaction->toAccount->decrement('balance', $transaction->amount);
                }
            }

            $transaction->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction: ' . $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Family;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
        ]);

        $family = Family::findOrFail($request->family_id);

        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $accounts = $family->accounts()->where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank,e-wallet,credit-card',
            'balance' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'icon' => 'sometimes|string|max:50',
            'color' => 'sometimes|string|max:7',
        ]);

        $family = Family::findOrFail($request->family_id);

        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $account = Account::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully',
            'data' => $account,
        ], 201);
    }

    public function show(Request $request, Account $account)
    {
        // Check if user is member of the family
        if (!$account->family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $account->load('transactions'),
        ]);
    }

    public function update(Request $request, Account $account)
    {
        // Check if user is member of the family
        if (!$account->family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:cash,bank,e-wallet,credit-card',
            'balance' => 'sometimes|numeric',
            'icon' => 'sometimes|string|max:50',
            'color' => 'sometimes|string|max:7',
            'is_active' => 'sometimes|boolean',
        ]);

        $account->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully',
            'data' => $account,
        ]);
    }

    public function destroy(Request $request, Account $account)
    {
        // Check if user is owner of the family
        if ($account->family->owner_user_id != $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only family owner can delete accounts',
            ], 403);
        }

        // Soft delete by marking as inactive
        $account->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'sometimes|date',
            'note' => 'sometimes|string',
        ]);

        $fromAccount = Account::findOrFail($request->from_account_id);
        $toAccount = Account::findOrFail($request->to_account_id);

        // Check if user is member of both families
        if (!$fromAccount->family->members->contains($request->user()) ||
            !$toAccount->family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check sufficient balance
        if ($fromAccount->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
            ], 400);
        }

        // Create transfer transaction
        $transaction = \App\Models\Transaction::create([
            'family_id' => $fromAccount->family_id,
            'user_id' => $request->user()->id,
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'type' => 'transfer',
            'amount' => $request->amount,
            'date' => $request->date ?? now()->toDateString(),
            'note' => $request->note,
        ]);

        // Update balances
        $fromAccount->decrement('balance', $request->amount);
        $toAccount->increment('balance', $request->amount);

        return response()->json([
            'success' => true,
            'message' => 'Transfer completed successfully',
            'data' => $transaction->load(['account', 'toAccount']),
        ], 201);
    }
}

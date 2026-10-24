<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get families the user is a member of (via pivot)
        $families = $user->families()->with('members')->get();

        // Auto-repair: if user owns families but is not in the pivot table, re-attach them
        // This handles cases where pivot records were lost (re-migration, manual seeding, etc.)
        if ($families->isEmpty()) {
            $ownedFamilies = $user->ownedFamilies()->with('members')->get();

            foreach ($ownedFamilies as $family) {
                // Check if user is already in pivot (they shouldn't be, but double check)
                $alreadyAttached = $family->members->contains('id', $user->id);
                if (!$alreadyAttached) {
                    // Re-attach the owner to the pivot table
                    $family->members()->attach($user->id, [
                        'role'      => 'owner',
                        'joined_at' => $family->created_at,
                    ]);
                    \Illuminate\Support\Facades\Log::warning('Auto-repaired missing pivot for owner', [
                        'user_id'   => $user->id,
                        'family_id' => $family->id,
                    ]);
                }
            }

            // Re-fetch after repair
            if ($ownedFamilies->isNotEmpty()) {
                $families = $user->families()->with('members')->get();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $families,
            '_debug' => [
                'user_id'             => $user->id,
                'families_via_pivot'  => $families->count(),
                'owned_families'      => $user->ownedFamilies()->count(),
                'pivot_records'       => \Illuminate\Support\Facades\DB::table('family_user')
                                            ->where('user_id', $user->id)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'currency' => 'sometimes|string|size:3',
        ]);

        $family = Family::create([
            'owner_user_id' => $request->user()->id,
            'name' => $request->name,
            'currency' => $request->currency ?? 'IDR',
        ]);

        // Add owner as member
        $family->members()->attach($request->user()->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Family created successfully',
            'data' => $family->load('members'),
        ], 201);
    }

    public function show(Request $request, Family $family)
    {
        // Check if user is member
        if (!$family->members->contains($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $family->load(['members', 'accounts', 'categories']),
        ]);
    }

    public function update(Request $request, Family $family)
    {
        // Check if user is owner
        if ($family->owner_user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only owner can update family',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'currency' => 'sometimes|string|size:3',
        ]);

        $family->update($request->only(['name', 'currency']));

        return response()->json([
            'success' => true,
            'message' => 'Family updated successfully',
            'data' => $family,
        ]);
    }

    public function destroy(Request $request, Family $family)
    {
        // Check if user is owner
        if ($family->owner_user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only owner can delete family',
            ], 403);
        }

        $family->delete();

        return response()->json([
            'success' => true,
            'message' => 'Family deleted successfully',
        ]);
    }

    public function inviteMember(Request $request, Family $family)
    {
        // Check if user is owner
        if ($family->owner_user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only owner can invite members',
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if ($family->members->contains($request->user_id)) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member',
            ], 400);
        }

        $family->members()->attach($request->user_id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member invited successfully',
            'data' => $family->load('members'),
        ]);
    }

    public function removeMember(Request $request, Family $family, $userId)
    {
        // Check if user is owner
        if ($family->owner_user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only owner can remove members',
            ], 403);
        }

        if ($userId == $family->owner_user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove owner',
            ], 400);
        }

        $family->members()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
        ]);
    }
}

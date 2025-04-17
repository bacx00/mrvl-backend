<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get();
        $result = $users->map(fn($u) => [
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'roles' => $u->getRoleNames()
        ]);
        return response()->json($result);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|string']);
        $role = $data['role'];
        if (!in_array($role, ['admin', 'user'])) {
            return response()->json(['message' => 'Invalid role'], 422);
        }
        $user->syncRoles([$role]);
        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()
        ]);
    }
}
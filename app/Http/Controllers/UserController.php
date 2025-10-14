<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => "error",
                'errors' => $validator->errors()
            ], 422);
        }



        $user = User::find($id);
        $roles = $user->getRoleNames(); // Get all role names the user has
        foreach ($roles as $role) {
            $user->removeRole($role);
        }

        $user->assignRole($request->roles);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update([
            'name' => $request->name,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone ?? null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }


    public function usersByRole($role)
    {
        $users = User::role($role)->select('id', 'name', 'full_name', 'status')->get();
        return $users;
    }



    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
        } else {
            session()->flash('error', __("Informations d'identification non valides"));
        }
    }


    public function updatePassword(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' =>  $validator->errors()->first()
            ], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        // Update the password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return back()->with('success', 'Mot de passe mis à jour avec succès!');
    }

    /**
     * Update password for specific user (Admin function)
     */
    public function updateUserPassword(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' =>  $validator->errors()->first()
            ], 422);
        }

        $user = User::findOrFail($userId);

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès']);
    }



    public function usersActions(){
        return User::withCount(['movements', ]);
    }


    public function documents() {
        return Document::with('docentete:DO_Reliquat,DO_Piece,DO_Ref,DO_Tiers,cbMarq,DO_Date,DO_DateLivr,DO_Expedit')
            ->whereIn('id', auth()->user()->lines->pluck('document_id'))
            ->paginate(35);
    }

}

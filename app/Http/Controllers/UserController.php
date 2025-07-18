<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        foreach($roles as $role) {
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


    public function usersByRole($role){
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

}

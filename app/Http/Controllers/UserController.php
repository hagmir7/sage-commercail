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
            'name' => 'required|string|max:255|unique:users,name,' . $id,
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'company_id' => 'nullable|numeric|exists:companies,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($id);
        $roles = $user->getRoleNames();
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
            'company_id' => $request->company_id ?? null
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
            ->orWhere("transfer_by", auth()->id())
            ->orderByDesc('id')
            ->paginate(35);
    }


    public function showDocument($piece){
        return Document::with(['lines' => function($lines){
            if(auth()->user()->hasRole(['preparation_cuisine', 'preparation_trailer', 'fabrication', 'montage', 'magasinier','chargement'])){
                return $lines->whereIn("id", auth()->user()->lines->pluck('id'));
            }elseif(auth()->user()->hasRole(['preparation', 'controleur'])){
                return $lines->where("company_id", auth()->user()->company_id);
            }
            
        }])->where('piece', $piece)->first();
    }


    public function destroy(User $user)
    {
        if (auth()->user()->hasRole('admin')) {
            $user->update([
                'is_active' => false
            ]);
            return response()->json([
                'message' => "Utilisateur supprimé avec succès"
            ]);
        }

        return response()->json(['message' => "You are not unauthorized"], 403);
    }



}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
{
    

     if (!auth('sanctum')->check()) {
        return response()->json([
            'message' => 'Vous devez être connecté pour accéder à cette ressource'
        ], 401);
    }


    $request->validate([
        'nom' => 'required|string',
        'prenom' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:6',
        'role' => 'required|in:admin,employe,responsablestock',
        'poste' => 'required_if:role,employe|string',
        'organigramme_id' => 'required|exists:organigrammes,id',

    ]);
    $fullName = $request['prenom'] . ' ' . $request['nom'];

    // Vérifie qu'il n'existe qu'un seul responsable du stock
    if ($request->role === 'responsablestock') {
        $existe = User::where('role', 'responsablestock')->exists();
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Il existe déjà un responsable du stock .'
            ], 409);
        }
    }

    // Vérifie qu'il n'existe qu'un seul administrateur
    if ($request->role === 'admin') {
        $existe = User::where('role', 'admin')->exists();
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Il y a possibilité d\'avoir un seul administrateur.'
            ], 409);
        }
    }

    // Créer d'abord l'utilisateur
    $user = User::create([
        'name' => $fullName, // ici on insère le fullname
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'role' => $request->role,
        'organigramme_id' => $request->organigramme_id,

    ]);

    // Ensuite, si c'est un employé, créer la ligne dans la table employes
    if ($request->role === 'employe') {
        $user->employe()->create([
            'poste' => $request->poste
        ]);
    }

    return response()->json([
        'message' => 'Utilisateur ajouté avec succès',
        'user' => $user->load('employe')  // optionnel : renvoyer avec ses infos employé
    ]);
}

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }
}

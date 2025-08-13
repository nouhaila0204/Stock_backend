<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Organigramme;


class AdminController extends Controller
{
    // 🔍 Voir tous les utilisateurs
    public function indexUser()
    {
        return User::all();
    }

    
    // ✏ Modifier un utilisateur
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $user->update($request->only(['name', 'email', 'role']));

        return response()->json(['message' => 'Utilisateur mis à jour', 'user' => $user]);
    }

    // ❌ Supprimer un utilisateur
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
    }



    
// 📋 Voir tous les éléments de l'organigramme
public function organigrammeIndex()
{
    $organigrammes = Organigramme::all();

    return response()->json([
        'organigrammes' => $organigrammes
    ]);
}

// ➕ Ajouter un élément à l'organigramme

public function organigrammeStore(Request $request)
{
    $request->validate([
        'nom' => 'required|string',
        'type' => 'required|string',
        'parent_id' => 'nullable|exists:organigrammes,id'
    ]);

    $org = Organigramme::create([
        'nom' => $request->nom,
        'type' => $request->type,
        'parent_id' => $request->parent_id
    ]);

    return response()->json([
        'message' => 'Élément ajouté avec succès',
        'organigramme' => $org
    ], 201);
}

// ✏ Modifier un élément de l'organigramme
public function organigrammeUpdate(Request $request, $id)
{
    $request->validate([
        'nom' => 'sometimes|required|string',
        'type' => 'sometimes|required|string',
        'parent_id' => 'nullable|exists:organigrammes,id|not_in:' . $id,
    ]);

    $org = Organigramme::findOrFail($id);

    // Mise à jour des champs si présents dans la requête
    if ($request->has('nom')) {
        $org->nom = $request->nom;
    }

    if ($request->has('type')) {
        $org->type = $request->type;
    }

    if ($request->has('parent_id')) {
        $org->parent_id = $request->parent_id;
    }

    $org->save();

    return response()->json([
        'message' => 'Élément mis à jour avec succès',
        'organigramme' => $org
    ]);
}

// ❌ Supprimer un élément de l'organigramme
public function organigrammeShow($id)
{
    $org = Organigramme::findOrFail($id);

    return response()->json([
        'organigramme' => $org
    ]);
}
public function organigrammeDestroy($id)
{
    $org = Organigramme::findOrFail($id);
    $org->delete();

    return response()->json([
        'message' => 'Élément supprimé avec succès'
    ]);
}
public function organigrammeSearch(Request $request)
{
    $request->validate([
        'nom' => 'nullable|string',
        'type' => 'nullable|string',
    ]);

    $query = Organigramme::query();

    if ($request->filled('nom')) {
        $query->where('nom', 'like', '%' . $request->nom . '%');
    }

    if ($request->filled('type')) {
        $query->where('type', 'like', '%' . $request->type . '%');
    }

    $results = $query->get();

    return response()->json([
        'message' => 'Résultats de la recherche',
        'data' => $results
    ]);
}
    

}
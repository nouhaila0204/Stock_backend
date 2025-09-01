<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employe;
use Illuminate\Http\Request;
use App\Models\Organigramme;


class AdminController extends Controller
{
    // 🔍 Voir tous les utilisateurs
public function indexUser()
{
    try {
        // Charger les utilisateurs avec leur relation organigramme et les parents
        $users = User::with(['organigramme', 'organigramme.parent', 'organigramme.parent.parent'])->get();
        
        $users->transform(function ($user) {
            $organigramme = $user->organigramme;
            
            $direction = null;
            $departement = null;
            $division = null;
            $agence = null;
            
            if ($organigramme) {
                // Déterminer le type de nœud et ses parents
                switch ($organigramme->type) {
                    case 'DirectionG':
                        $direction = 'Direction Générale';
                        break;
                        
                    case 'Direction':
                        $direction = $organigramme->nom;
                        break;
                        
                    case 'Département':
                        $departement = $organigramme->nom;
                        // Trouver la direction parente
                        if ($organigramme->parent) {
                            if ($organigramme->parent->type === 'Direction') {
                                $direction = $organigramme->parent->nom;
                            } elseif ($organigramme->parent->type === 'DirectionG') {
                                $direction = 'Direction Générale';
                            }
                        }
                        break;
                        
                    case 'Division':
                    case 'Mission':
                        $division = $organigramme->nom;
                        // Trouver le département et la direction parents
                        $current = $organigramme;
                        while ($current->parent) {
                            if ($current->parent->type === 'Département') {
                                $departement = $current->parent->nom;
                            } elseif ($current->parent->type === 'Direction') {
                                $direction = $current->parent->nom;
                                break;
                            } elseif ($current->parent->type === 'DirectionG') {
                                $direction = 'Direction Générale';
                                break;
                            }
                            $current = $current->parent;
                        }
                        break;
                        
                    case 'Agence':
                    case 'Unité':
                        $agence = $organigramme->nom;
                        // Trouver la hiérarchie complète
                        $current = $organigramme;
                        while ($current->parent) {
                            if ($current->parent->type === 'Division' || $current->parent->type === 'Mission') {
                                $division = $current->parent->nom;
                            } elseif ($current->parent->type === 'Département') {
                                $departement = $current->parent->nom;
                            } elseif ($current->parent->type === 'Direction') {
                                $direction = $current->parent->nom;
                                break;
                            } elseif ($current->parent->type === 'DirectionG') {
                                $direction = 'Direction Générale';
                                break;
                            }
                            $current = $current->parent;
                        }
                        break;
                }
                
                // Cas spécial: élément directement lié à la DG (parent_id = 1)
                if ($organigramme->parent_id === 1) {
                    $direction = 'Direction Générale';
                }
            }
            
            return [
                'id' => $user->id,
                'nomComplet' => $user->name,
                'email' => $user->email,
                'direction' => $direction ?? 'Non spécifié',
                'departement' => $departement ?? 'Non spécifié',
                'division' => $division ?? 'Non spécifié',
                'agence' => $agence ?? 'Non spécifié',
                'role' => $user->role,
                'poste' => $user->poste ?? $user->employe->poste ?? null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ];
        });
        
        return response()->json($users);
        
    } catch (\Exception $e) {
        \Log::error('Error in indexUser: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erreur lors du chargement des utilisateurs',
            'message' => $e->getMessage()
        ], 500);
    }
}



// ✏ Modifier un utilisateur avec l'ID d'organigramme
public function updateUser(Request $request, $id)
{
    try {
        $user = User::findOrFail($id);

        // Valider les données
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'role' => 'sometimes|string|max:255',
            'poste' => 'nullable|string|max:255',
            'direction' => 'nullable|string|max:255',
            'departement' => 'nullable|string|max:255',
            'division' => 'nullable|string|max:255',
            'agence' => 'nullable|string|max:255',
            'organigramme_id' => 'nullable|integer|exists:organigrammes,id' // Validation de l'ID organigramme
        ]);

        // Mettre à jour l'utilisateur AVEC l'ID d'organigramme
        $user->update([
            'name' => $validatedData['name'] ?? $user->name,
            'prenom' => $validatedData['prenom'] ?? $user->prenom,
            'email' => $validatedData['email'] ?? $user->email,
            'role' => $validatedData['role'] ?? $user->role,
            'direction' => $validatedData['direction'] ?? $user->direction,
            'departement' => $validatedData['departement'] ?? $user->departement,
            'division' => $validatedData['division'] ?? $user->division,
            'agence' => $validatedData['agence'] ?? $user->agence,
            'organigramme_id' => $validatedData['organigramme_id'] ?? $user->organigramme_id // ← ID organigramme
        ]);

        // Mettre à jour ou créer l'employé si le poste est fourni
        // Gérer le poste de manière plus safe
        if (isset($validatedData['poste'])) {
            // Vérifier si un employé existe déjà pour cet utilisateur
            $employe = Employe::where('user_id', $user->id)->first();
            
            if ($employe) {
                // Mettre à jour l'employé existant
                $employe->update(['poste' => $validatedData['poste']]);
            } else {
                // Créer un nouvel employé
                Employe::create([
                    'user_id' => $user->id,
                    'poste' => $validatedData['poste']
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $user
        ]);

    
    } catch (\Exception $e) {
        \Log::error('Erreur updateUser: ' . $e->getMessage());
        \Log::error('Trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour',
            'error' => $e->getMessage()
        ], 500);
    }
}

// 🔍 Afficher un utilisateur spécifique
public function showUser($id)
{
    try {
        $user = User::with('employe')->findOrFail($id);
        
        // Formater les données exactement comme le front-end les attend
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'poste' => $user->poste ?? $user->employe->poste ?? null,
            'direction' => $user->direction,
            'departement' => $user->departement,
            'division' => $user->division,
            'agence' => $user->agence,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Utilisateur non trouvé',
            'message' => $e->getMessage()
        ], 404);
    }
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
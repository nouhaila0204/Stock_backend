<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Demande;
use App\Models\Employe;
use App\Models\User;
use App\Models\Product;
use App\Models\SousFamilleProduit;
use App\Models\TVA;
use App\Models\FamilleProduit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController;
use App\Http\Resources\ProductResource;
use App\Http\Middleware\EmployeMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeController extends Controller
{
    // 📦 Consulter l'état du stock
    public function consulterStock()
{
    $produits = Product::with(['sousFamille.Famille'])
        ->select('id', 'name', 'stock', 'sous_famille_produit_id')
        ->get()
        ->map(function ($produit) {
            return [
                'nom_produit' => $produit->name,
                'quantite_stock' => $produit->stock,
                'sous_famille' => optional($produit->sousFamille)->nom,
                'famille' => optional(optional($produit->sousFamille)->Famille)->nom,
            ];
        });

    return response()->json($produits);
}


    public function consulterHistoriqueDemande()
{
    $employe = Employe::where('user_id', Auth::id())->first();

    if (!$employe) {
        return response()->json(['message' => 'Employé non trouvé'], 404);
    }

    // Charger la relation produit
    $demandes = Demande::with('produit')
        ->where('user_id', Auth::id())
        ->get();

    return response()->json($demandes);
}


    public function storeDemande(Request $request)
{
    $request->validate([
        'raison' => 'required|string|max:255',
        'produit_id' => 'required|exists:products,id',
        'quantite' => 'required|integer|min:1'
    ]);

    // Récupérer l'employé connecté
    $employe = \App\Models\Employe::where('user_id', Auth::id())->first();

    if (!$employe) {
        return response()->json(['message' => 'Employé non trouvé'], 404);
    }

    // Création de la demande
    $demande = \App\Models\Demande::create([
        'user_id'    => $employe->user_id,
        'raison'     => $request->raison,
        'produit_id' => $request->produit_id, // <-- direct
        'quantite'   => $request->quantite,
        'etat'       => 'en_attente',
    ]);

    return response()->json($demande, 201);
}


    // ✏ Mettre à jour une demande
    public function updateDemande(Request $request, $id)
    {
        $request->validate([
            'raison' => 'required|string|max:255',
            'produit' => 'nullable|string|exists:products,name',
            'quantite' => 'nullable|integer|min:1'
        ]);

        $employe = \App\Models\Employe::where('user_id', Auth::id())->first();

        if (!$employe) {
            return response()->json(['message' => 'Employé non trouvé'], 404);
        }

        $demande = Demande::where('id', $id)
                          ->where('user_id', $employe->id)
                          ->first();

        if (!$demande) {
            return response()->json(['message' => 'Demande non trouvée'], 404);
        }

        $demande->update(['raison' => $request->raison]);

        return response()->json($demande);
    }

    // ❌ Supprimer une demande
    public function deleteDemande($id)
    {
        $employe = \App\Models\Employe::where('user_id', Auth::id())->first();

        if (!$employe) {
            return response()->json(['message' => 'Employé non trouvé'], 404);
        }

        $demande = Demande::where('id', $id)
                          ->where('user_id', $employe->id)
                          ->first();

        if (!$demande) {
            return response()->json(['message' => 'Demande non trouvée'], 404);
        }

        $demande->delete();
        return response()->json(['message' => 'Demande supprimée avec succès']);
    }

    // 🔍 Voir une demande précise
    public function showDemande($id)
    {
        $employe = \App\Models\Employe::where('user_id', Auth::id())->first();

        if (!$employe) {
            return response()->json(['message' => 'Employé non trouvé'], 404);
        }

        $demande = Demande::where('id', $id)
                          ->where('user_id', Auth::id())
                          ->first();

        if (!$demande) {
            return response()->json(['message' => 'Demande non trouvée'], 404);
        }

        return response()->json($demande);
    }
}

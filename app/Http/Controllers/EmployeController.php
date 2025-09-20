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
use Illuminate\Support\Facades\DB;

class EmployeController extends Controller
{
    // 📋 Créer une nouvelle demande avec produits
    public function storeDemande(Request $request)
    {
        $request->validate([
            'raison' => 'required|string|max:255',
            'produits' => 'required|array|min:1',
            'produits.*.product_id' => 'required|exists:products,id',
            'produits.*.quantite' => 'required|integer|min:1'
        ]);

        $employe = Employe::where('user_id', Auth::id())->first();

        if (!$employe) {
            return response()->json(['message' => 'Employé non trouvé'], 404);
        }

        DB::beginTransaction();

        try {
            // Création de la demande
            $demande = Demande::create([
                'user_id' => $employe->user_id,
                'raison' => $request->raison,
                'etat' => 'en_attente',
            ]);

            // Attacher les produits avec leurs quantités
            foreach ($request->produits as $produit) {
                $demande->products()->attach($produit['product_id'], [
                    'quantite' => $produit['quantite']
                ]);
            }

            DB::commit();

            // Charger les relations pour la réponse
            $demande->load('products');

            return response()->json([
                'message' => 'Demande créée avec succès',
                'demande' => $demande
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création de la demande'], 500);
        }
    }

    // ✏ Mettre à jour une demande
    public function updateDemande(Request $request, $id)
    {
        $request->validate([
            'raison' => 'sometimes|string|max:255',
            'produits' => 'sometimes|array|min:1',
            'produits.*.product_id' => 'required_with:produits|exists:products,id',
            'produits.*.quantite' => 'required_with:produits|integer|min:1'
        ]);

        $employe = Employe::where('user_id', Auth::id())->first();

        if (!$employe) {
            return response()->json(['message' => 'Employé non trouvé'], 404);
        }

        $demande = Demande::where('id', $id)
                          ->where('user_id', $employe->user_id)
                          ->first();

        if (!$demande) {
            return response()->json(['message' => 'Demande non trouvée'], 404);
        }

        DB::beginTransaction();

        try {
            // Mettre à jour la raison si fournie
            if ($request->has('raison')) {
                $demande->update(['raison' => $request->raison]);
            }

            // Mettre à jour les produits si fournis
            if ($request->has('produits')) {
                $demande->products()->detach();
                
                foreach ($request->produits as $produit) {
                    $demande->products()->attach($produit['product_id'], [
                        'quantite' => $produit['quantite']
                    ]);
                }
            }

            DB::commit();

            $demande->load('products');

            return response()->json([
                'message' => 'Demande mise à jour avec succès',
                'demande' => $demande
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    // ❌ Supprimer une demande
    public function deleteDemande($id)
    {
        $employe = Employe::where('user_id', Auth::id())->first();

        if (!$employe) {
            return response()->json(['message' => 'Employé non trouvé'], 404);
        }

        $demande = Demande::where('id', $id)
                          ->where('user_id', $employe->user_id)
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
        $employe = Employe::where('user_id', Auth::id())->first();

        if (!$employe) {
            return response()->json(['message' => 'Employé non trouvé'], 404);
        }

        $demande = Demande::with('products')
                          ->where('id', $id)
                          ->where('user_id', $employe->user_id)
                          ->first();

        if (!$demande) {
            return response()->json(['message' => 'Demande non trouvée'], 404);
        }

        return response()->json($demande);
    }

    // 📊 Consulter le stock
    public function consulterStock()
    {
        $produits = Product::with(['sousFamille.Famille'])
            ->select('id', 'name', 'stock', 'sous_famille_produit_id')
            ->get()
            ->map(function ($produit) {
                return [
                    'id' => $produit->id,
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

    $demandes = Demande::with(['products'])
        ->where('user_id', $employe->user_id)
        ->get()
        ->map(function ($demande) {
            $noms = $demande->products->pluck('name')->toArray();
            $quantites = $demande->products->pluck('pivot.quantite')->toArray();

            return [
                'id' => $demande->id,
                'raison' => $demande->raison,
                'etat' => $demande->etat,
                'created_at' => $demande->created_at,
                'noms_produits' => $noms,
                'quantites' => $quantites
            ];
        });

    return response()->json($demandes);
}
}
   /* // 📦 Consulter l'état du stock
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
    }*/



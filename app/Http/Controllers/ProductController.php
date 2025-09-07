<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Entree;
use App\Models\Stock;
use App\Models\Sortie;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductDetailCollection;
use App\Http\Resources\ProductSearchResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Alerte; 

class ProductController extends Controller
{
   public function index()
{
    return Product::with(['tva', 'sousFamille.famille'])->get();
}

/**
     * Afficher les détails d'un produit spécifique.
     *
     * @param Product $product
     * @return JsonResponse
     */
   public function showProduit(Product $product): JsonResponse
{
    // Charger les relations
    $product->load(['tva', 'sousFamille', 'sousFamille.famille']);

    // Retourner une réponse JSON standardisée
    return response()->json([
        'status' => 'success',
        'data' => $product
    ], 200);
}



    //pour employee
    public function indexProduit()
    {
        return Product::select(['id', 'name'])->get();
    }

    // Ajouter un produit
    public function ajouterProduit(Request $request)
    {

    $fields = $request->validate([
        'name' => 'required|string',
        'reference' => 'required|string|unique:products',
        'numBond' => 'required|string|max:255',
        'codeMarche' => 'required|string',
        'date' => 'required|date',
        'stock' => 'required|integer',
        'stock_min' => 'required|integer',
        'price' => 'required|numeric',
        'tva_id' => 'required|exists:tvas,id',
        'sous_famille_produit_id' => 'required|exists:sous_famille_produits,id',
        'fournisseur_id' => 'required|exists:fournisseurs,id' // Si vous souhaitez lier à un fournisseur
    ]);

    $produit = Product::create($fields);

    // Charger les relations
    $produit->load([
        'tva',
        'sousFamille.famille' // on remonte à la famille aussi
    ]);

    // Étape 3 : Créer une entrée initiale
    $entree = Entree::create([
        'produit_id' => $produit->id,
        'quantite' => $fields['stock'],
        'prixUnitaire' => $fields['price'],
        'numBond' => $fields['numBond'],
        'codeMarche' => $fields['codeMarche'] , // Si vous souhaitez lier à un marché
        'date' => $fields['date'],
        'fournisseur_id' => $fields['fournisseur_id'] // Si vous souhaitez lier à un fournisseur
    ]);

    // Étape 4 : Créer ou mettre à jour le stock
    $stock = new Stock();
    $stock->produit_id = $produit->id;
    $stock->qteEntree = $fields['stock'];
    $stock->qteSortie = 0;
    $stock->valeurStock = $fields['stock'] * $fields['price'];
    $stock->save();

    return response()->json([
        'message' => 'Produit ajouté avec succès, entrée et stock initial créés.',
        'produit' => $produit,
        'entree' => $entree,
        'stock' => $stock
    ], 201);
}

    // Supprimer un produit
   public function suppProduit($id)
{

    $produit = Product::findOrFail($id);

    /* 🚫 Vérifier qu’il n’a pas d’entrées ou de sorties liées
    $hasEntrees = Entree::where('produit_id', $id)->exists();
    $hasSorties = Sortie::where('produit_id', $id)->exists();

    if ($hasEntrees || $hasSorties) {
        return response()->json([
            'message' => 'Impossible de supprimer ce produit car il a des entrées ou des sorties associées.'
        ], 400);
    }*/

    // ✅ Supprimer le stock lié s’il existe
    Stock::where('produit_id', $id)->delete();

    // ✅ Supprimer le produit
    $produit->delete();

    return response()->json(['message' => 'Produit supprimé avec succès']);
}

public function updateProduit(Request $request, $id)
    {
        $produit = Product::findOrFail($id);

        // Sauvegarder l'ancien stock
        $oldStock = $produit->stock;

        // Validation des champs
        $fields = $request->validate([
            'name' => 'sometimes|required|string',
            'reference' => 'sometimes|required|string|unique:products,reference,' . $produit->id,
            'stock' => 'sometimes|required|integer|min:0',
            'stock_min' => 'sometimes|required|integer|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'tva_id' => 'sometimes|required|exists:tvas,id',
            'sous_famille_produit_id' => 'sometimes|required|exists:sous_famille_produits,id'
        ]);

        // Mettre à jour les champs simples du produit
        $produit->fill($fields);

        // Gestion spéciale du stock
        if ($request->has('stock')) {
            $newStockValue = $request->stock;

            if ($oldStock == 0) {
                // Cas 1 : ancien stock = 0 → on additionne
                $produit->stock = $oldStock + $newStockValue;
            } else {
                // Cas 2 : ancien stock > 0 → on remplace
                $produit->stock = $newStockValue;
            }
        }

        $produit->save();

        // Recharger valeurs après mise à jour
        $newStock = $produit->stock;
        $newPrice = $produit->price;

        // 🔹 Synchroniser la table STOCKS
        $stock = Stock::where('produit_id', $produit->id)->first();
        if ($stock) {
            $stock->qteEntree = $newStock;
            $stock->valeurStock = $newStock * $newPrice;
            $stock->save();
        }

        // 🔹 Synchroniser la table ENTREES
        $entree = Entree::where('produit_id', $produit->id)->first();
        if ($entree) {
            $entree->quantite = $newStock;
            $entree->prixUnitaire = $newPrice;
            $entree->save();
        }

        // 🔹 Supprimer l'alerte si le stock dépasse stock_min
        $alerte = Alerte::where('produit_id', $produit->id)->first();
        if ($alerte && $newStock > $produit->stock_min) {
            try {
                $alerte->delete();
            } catch (\Exception $e) {
                \Log::error('Erreur lors de la suppression de l\'alerte pour le produit ID ' . $produit->id . ': ' . $e->getMessage());
            }
        }

        // Charger les relations pour la réponse
        $produit->load(['tva', 'sousFamille']);

        // 🔹 Ajouter une notification si l'alerte est supprimée
        $message = 'Produit mis à jour et synchronisé avec stock et entrée avec succès';
        if ($alerte && $newStock > $produit->stock_min) {
            $message .= ', alerte supprimée';
        }

        return response()->json([
            'message' => $message,
            'produit' => $produit
        ]);
    }

   /*public function updateProduit(Request $request, $id) 
{

    $produit = Product::findOrFail($id);

    // Sauvegarder les anciennes valeurs AVANT la mise à jour
    $oldStock = $produit->stock;
    $oldPrice = $produit->price;

    // Valider les champs
    $fields = $request->validate([
        'name' => 'sometimes|required|string',
        'reference' => 'sometimes|required|string|unique:products,reference,' . $produit->id,
        'stock' => 'sometimes|required|integer',
        'stock_min' => 'sometimes|required|integer',
        'price' => 'sometimes|required|numeric',
        'tva_id' => 'sometimes|required|exists:tvas,id',
        'sous_famille_produit_id' => 'sometimes|required|exists:sous_famille_produits,id'
    ]);

    // Mettre à jour le produit
    $produit->update($fields);

    // ⚠️ Recharger les valeurs mises à jour
    $newStock = $produit->stock;
    $newPrice = $produit->price;


    // Mettre à jour la table STOCKS
    $stock = Stock::where('produit_id', $produit->id)->first();
    if ($stock) {
        $stock->qteEntree = $newStock;
        $stock->valeurStock = $stock->qteEntree * $newPrice;
        $stock->save();
    }

    // Mettre à jour la table ENTREES
    $entree = Entree::where('produit_id', $produit->id)->first();
    if ($entree) {
        $entree->quantite = $newStock;
        $entree->prixUnitaire = $newPrice;
        $entree->save();
    }

    // Charger les relations
    $produit->load(['tva', 'sousFamille']);

    return response()->json([
        'message' => 'Produit et données liées (stock et entrée) mis à jour avec succès',
        'produit' => $produit
    ]);
}

    public function showProduit(Product $product)
    {
        return $product->load(['tva', 'sousFamille']);
    }*/

   

// 🔍 Rechercher produit par mot-clé - CORRIGÉ
public function search(Request $request)
{
    $query = Product::with(['sousFamille', 'sousFamille.famille', 'tva']);

    // 🔍 Recherche par nom ou référence (utilisation de OR)
    if ($request->has('name') || $request->has('reference')) {
        $query->where(function ($q) use ($request) {
            if ($request->has('name')) {
                $q->where('name', 'like', '%' . $request->name . '%');
            }
            if ($request->has('reference')) {
                $q->orWhere('reference', 'like', '%' . $request->reference . '%');
            }
        });
    }

    // 🔍 Recherche par sous_famille_produit_id
    if ($request->has('sous_famille_id')) {
        $query->where('sous_famille_produit_id', $request->sous_famille_id);
    }

    // 🔍 Recherche par famille_id
    if ($request->has('famille_id')) {
        $query->whereHas('sousFamille.famille', function ($q) use ($request) {
            $q->where('id', $request->famille_id);
        });
    }

    // 🔍 Recherche par stock critique (inférieur au minimum)
    if ($request->has('seuil_critique') && $request->seuil_critique == true) {
        $query->whereColumn('stock', '<=', 'stock_min');
    }

    $produits = $query->get();
    \Log::info('Params: ' . json_encode($request->all()));
    \Log::info('Results: ' . $produits->count());

    return response()->json($produits);
}

}
<?php

namespace App\Http\Controllers;

use App\Models\Entree;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;


class EntreeController extends Controller
{
    // 📥 Liste des entrées
    public function showEntree()
    {

        $entrees = Entree::with('produit')->latest()->get();
        return response()->json($entrees);
    }

    // ➕ Ajouter une entrée de stock
    public function ajouterEntree(Request $request)
    {

        $request->validate([
            'produit_id' => 'required|exists:products,id',
            'numBond' => 'required|string|max:255',
            'codeMarche' => 'nullable|string',
            'prixUnitaire' => 'required|numeric|min:0',
            'quantite' => 'required|integer|min:1',
            'date' => 'required|date',
            'fournisseur_id' => 'required|exists:fournisseurs,id', // Si vous souhaitez lier à un fournisseur
        ]);

        $entree = Entree::create([
            'produit_id' => $request->produit_id,
            'prixUnitaire' => $request->prixUnitaire,
            'quantite' => $request->quantite,
            'numBond' => $request->numBond,
            'codeMarche' => $request->codeMarche,
            'date' => $request->date,
            'fournisseur_id' => $request->fournisseur_id
        ]);

        // Mettre à jour la table stocks
    $stock = Stock::where('produit_id', $request->produit_id)->first();

    if (!$stock) {
        // Créer un nouveau stock si pas encore créé
        $stock = new Stock();
        $stock->produit_id = $request->produit_id;
        $stock->qteEntree = 0;
        $stock->qteSortie = 0;
        $stock->valeurStock = 0;
    }

    // Ajouter la quantité entrée
    $stock->qteEntree += $request->quantite;

    // Mise à jour du stock du produit
    $product = Product::find($request->produit_id);
    $product->stock += $request->quantite;
    $product->save();


    // Recalculer valeurStock si nécessaire (par exemple, prixUnitaire * stock net)
    $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $request->prixUnitaire;

    $stock->save();

    return response()->json([
        'message' => 'Entrée enregistrée et stock mis à jour',
        'data' => $entree
    ], 201);
    } 


    // Supprimer une entrée
   public function suppEntree($id)
{

    $entree = Entree::findOrFail($id);

    // 🔁 Mise à jour du stock dans la table `products`
    $product = Product::find($entree->produit_id);
    $product->stock -= $entree->quantite;
    $product->save();

    // 🔁 Mise à jour du stock dans la table `stocks`
    $stock = Stock::where('produit_id', $entree->produit_id)->first();
    if ($stock) {
        $stock->qteEntree -= $entree->quantite;
        // Recalcul valeur stock (en gardant le même prix unitaire que l'entrée supprimée)
        $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $entree->prixUnitaire;
        $stock->save();
    }

    // ✅ Supprimer l’entrée
    $entree->delete();

    return response()->json(['message' => 'Entrée supprimée et stock mis à jour']);
}

// Mettre à jour une entrée
   public function updateEntree(Request $request, $id)
{

    $entree = Entree::findOrFail($id);

    $request->validate([
        'produit_id' => 'sometimes|exists:products,id',
        'numBond' => 'sometimes|string|max:255',
        'prixUnitaire' => 'sometimes|numeric|min:0',
        'quantite' => 'sometimes|integer|min:1',
        'date' => 'sometimes|date',
    ]);

    // Sauvegarder anciennes valeurs
    $oldQuantite = $entree->quantite;
    $oldProduitId = $entree->produit_id;

    // Préparer nouvelles valeurs
    $newProduitId = $request->produit_id ?? $oldProduitId;
    $newQuantite = $request->quantite ?? $oldQuantite;

    // 🔁 Vérifier stock négatif
    if ($oldProduitId != $newProduitId) {
        $oldProduct = Product::find($oldProduitId);
        $newProduct = Product::find($newProduitId);

        if ($oldProduct->stock - $oldQuantite < 0) {
            return response()->json(['message' => 'Stock insuffisant pour retirer l’ancienne quantité du produit initial'], 400);
        }

        // Appliquer modification
        $oldProduct->stock -= $oldQuantite;
        $oldProduct->save();

        $newProduct->stock += $newQuantite;
        $newProduct->save();
    } else {
        $product = Product::find($newProduitId);
        $diff = $newQuantite - $oldQuantite;

        if ($product->stock + $diff < 0) {
            return response()->json(['message' => 'Stock insuffisant pour effectuer cette modification'], 400);
        }

        $product->stock += $diff;
        $product->save();
    }

    // Mise à jour de l'entrée
    $entree->fill($request->all());
    $entree->save();

    // 🟢 Si prixUnitaire est modifié → mettre à jour product.price
    if ($request->has('prixUnitaire')) {
        $product = Product::find($entree->produit_id);
        $product->price = $request->prixUnitaire;
        $product->save();
    }


    // ✅ (Optionnel) mettre à jour stock global (table `stocks`)
    $stock = Stock::where('produit_id', $newProduitId)->first();
    if ($stock) {
        if ($oldProduitId != $newProduitId) {
            $oldStock = Stock::where('produit_id', $oldProduitId)->first();
            if ($oldStock) {
                $oldStock->qteEntree -= $oldQuantite;
                $oldStock->valeurStock = ($oldStock->qteEntree - $oldStock->qteSortie) * $oldProduct->price;
                $oldStock->save();
            }

            $stock->qteEntree += $newQuantite;
        } else {
            $stock->qteEntree += ($newQuantite - $oldQuantite);
        }

        $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $product->price;
        $stock->save();
    }

    return response()->json([
        'message' => 'Entrée mise à jour avec ajustement du stock',
        'data' => $entree
    ]);
}

    // 🔍 Filtrer les entrées par produit_id
    public function filtrer(Request $request)
{
    $query = Entree::with('produit');

    // Filtrer par nom du produit
    if ($request->has('produit_nom') && $request->produit_nom !== null) {
        $query->whereHas('produit', function ($q) use ($request) {
            $q->where('name', 'like', '%' . $request->produit_nom . '%');
        });
    }

    if ($request->filled('numBond')) {
        $query->where('numBond', 'like', '%' . $request->numBond . '%');
    }

    if ($request->filled('date')) {
        $query->whereDate('date', $request->date);
    }

    return response()->json($query->get(), 200);
}


    public function afficherBonEntree($id)
{
    $entree = Entree::with(['produit'])->findOrFail($id);
    // Récupérer le nom du responsable connecté
    $responsablestock = Auth::user()->name . ' ' . Auth::user()->prenom;
    return view('pdf.bon_entree', compact('entree', 'responsablestock'));
}




public function imprimerBon($id)
{
    try {
        $user = Auth::user();
        if (!$user || !$user->isResponsableStock()) {
            return response()->json([
                'error' => 'Utilisateur non autorisé'
            ], 403);
        }

        $entree = Entree::with('produit')->findOrFail($id);

        if (!$entree->produit) {
            return response()->json([
                'error' => 'Aucun produit trouvé pour cette entrée'
            ], 404);
        }

        $responsable = $user->name ?? $user->nom ?? 'Responsable inconnu';

        $pdf = Pdf::loadView('pdf.bon_entree', [
            'entree' => $entree, // ✅ Solution
            'responsablestock' => $responsable, // ✅ utilisé dans la vue
        ]);

        return $pdf->stream('bon_entree_'.$id.'.pdf');

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Erreur lors de la génération du PDF',
            'message' => $e->getMessage()
        ], 500);
    }
}


 
}

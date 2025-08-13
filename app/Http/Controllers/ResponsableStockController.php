<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\Stock;
use App\Models\Entree;
use App\Models\Sortie;
use App\Models\Product;
use App\Models\TVA;
use App\Models\User;
use App\Models\Employe;
use App\Models\Organigramme;
use App\Models\SousFamilleProduit;
use App\Models\Fournisseur;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class ResponsableStockController extends Controller
{

public function statistiquesGlobales()
{
    // Total produits (COUNT)
    $totalProduits = Product::count();

    // Produits en rupture (stock <= 5)
    $ruptures = Product::select('id', 'name', 'stock')
        ->where('stock', '<=', 5)
        ->get();

    // Produits en alerte (stock <= stock_min)
    $alertes = Product::select('id', 'name', 'stock', 'stock_min')
        ->whereColumn('stock', '<=', 'stock_min')
        ->get();

    // Top 5 produits les plus demandés
    $topProduits = Product::select('products.id', 'products.name')
        ->withCount('sorties')
        ->orderByDesc('sorties_count')
        ->take(5)
        ->get();

    // Fournisseur le plus utilisé (sans charger tous les produits)
    $fournisseurPlusUtilise = \DB::table('entrees')
        ->join('fournisseurs', 'entrees.fournisseur_id', '=', 'fournisseurs.id')
        ->select('fournisseurs.raisonSocial', \DB::raw('COUNT(entrees.produit_id) as total'))
        ->groupBy('fournisseurs.id', 'fournisseurs.raisonSocial')
        ->orderByDesc('total')
        ->first();

    // TVA la plus utilisée
    $tvaPlusUtilisee = \DB::table('products')
        ->join('tvas', 'products.tva_id', '=', 'tvas.id')
        ->select('tvas.taux', \DB::raw('COUNT(products.id) as total'))
        ->groupBy('tvas.id', 'tvas.taux')
        ->orderByDesc('total')
        ->first();

    return response()->json([
        'total_produits' => $totalProduits,
        'produits_en_rupture' => $ruptures,
        'produits_en_alerte' => $alertes,
        'produits_les_plus_demandes' => $topProduits,
        'fournisseur_plus_utilise' => $fournisseurPlusUtilise ? $fournisseurPlusUtilise->raisonSocial : null,
        'tva_plus_utilisee' => $tvaPlusUtilisee ? $tvaPlusUtilisee->taux : null,
    ]);
}




// 📦 Consulter les alertes du stock
    public function voirAlertes()
{
    // Récupérer tous les produits avec stock <= stock_min
    $productsEnAlerte = Product::whereColumn('stock', '<=', 'stock_min')->get();

    return response()->json([
        'message' => 'Alertes de stock faible',
        'alertes' => $productsEnAlerte
]);
}




// 📋 Gérer les fournisseurs
public function indexFournisseurs()
{
    return response()->json(Fournisseur::all());
}

public function storeFournisseur(Request $request)
{
    $request->validate([
        'raisonSocial' => 'required|string',
        'email' => 'required|email|unique:fournisseurs,email',
    ]);

    $fournisseur = Fournisseur::create($request->only('raisonSocial', 'email'));
    return response()->json(['message' => 'Fournisseur ajouté avec succès', 'data' => $fournisseur], 201);
}

public function updateFournisseur(Request $request, $id)
{
    $fournisseur = Fournisseur::findOrFail($id);

    $request->validate([
        'raisonSocial' => 'required|string',
        'email' => 'required|email|unique:fournisseurs,email,' . $id,
    ]);

    $fournisseur->update($request->only('raisonSocial', 'email'));
    return response()->json(['message' => 'Fournisseur modifié avec succès', 'data' => $fournisseur]);
}
public function destroyFournisseur($id)
{
    $fournisseur = Fournisseur::findOrFail($id);
    $fournisseur->delete();
    return response()->json(['message' => 'Fournisseur supprimé avec succès']);
}




    //Validation des demandes
    public function validerDemande($id)
    {

        $demande = Demande::findOrFail($id);
        $demande->etat = 'validée';
        $demande->save();

        return response()->json(['message' => 'Demande validée avec succès']);
    }

    // ❌ Refuser une demande
    public function refuserDemande($id)
    {

        $demande = Demande::findOrFail($id);
        $demande->etat = 'refusée';
        $demande->save();

        return response()->json(['message' => 'Demande refusée avec succès']);
    }

    // 🔁 Changer l'état d'une demande (optionnel)
    public function changerEtatDemande($id, Request $request)
    {

        $request->validate([
            'etat' => 'required|in:en_attente,validée,refusée',
        ]);

        $demande = Demande::findOrFail($id);
        $demande->etat = $request->etat;
        $demande->save();

        return response()->json(['message' => 'État de la demande mis à jour avec succès']);
    }

    // 📋 Lister toutes les demandes par état
    public function demandesParEtat($etat)
    {

        if (!in_array($etat, ['en_attente', 'validée', 'refusée'])) {
            return response()->json(['message' => 'État invalide'], 400);
        }

        $demandes = Demande::with('produit', 'user')
            ->where('etat', $etat)
            ->get();

        return response()->json($demandes);
    }

    

    // 📥 Liste des entrées
    /*public function showEntree()
    {
        $this->autoriserResponsable();

        $entrees = Entree::with('produit')->latest()->get();
        return response()->json($entrees);
    }

    // ➕ Ajouter une entrée de stock
    public function ajouterEntree(Request $request)
    {
        $this->autoriserResponsable();

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
    $this->autoriserResponsable();

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
    $this->autoriserResponsable();

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
}*/








// Ajouter une sortie de stock
    /*public function ajouterSortie(Request $request)
    {
        $this->autoriserResponsable();

        $request->validate([
            'produit_id' => 'required|exists:products,id',
            'destination' => 'required|string|max:255',
            'quantite' => 'required|integer|min:1',
            'date' => 'required|date',
        ]);

        $product = Product::find($request->produit_id);

        // Vérifier la quantité en stock

        if ($product->stock < $request->quantite) {
            return response()->json(['message' => 'Quantité insuffisante en stock'], 400);
        }

        $sortie = Sortie::create([
            'produit_id' => $request->produit_id,
            'destination' => $request->destination,
            'quantite' => $request->quantite,
            'date' => $request->date,
        ]);

        // Mettre à jour le stock du produit
        $product->stock -= $request->quantite;
        $product->save();


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

    // Ajouter la quantité sortie
    $stock->qteSortie += $request->quantite;

    // Recalculer valeurStock si nécessaire (par exemple, prixUnitaire * stock net)
    $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $request->prixUnitaire;

    $stock->save();

        return response()->json([
            'message' => 'Sortie enregistrée',
            'data' => $sortie
        ], 201);
    }


    // Supprimer une sortie
    public function suppSortie($id)
{
    $this->autoriserResponsable();

    $sortie = Sortie::findOrFail($id);

    // 🔁 Remettre la quantité dans le stock du produit
    $product = Product::find($sortie->produit_id);
    $product->stock += $sortie->quantite;
    $product->save();

    // 🔁 Mise à jour dans la table `stocks`
    $stock = Stock::where('produit_id', $sortie->produit_id)->first();
    if ($stock) {
        $stock->qteSortie -= $sortie->quantite;
        $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $product->price;
        $stock->save();
    }

    // 🗑️ Supprimer la sortie
    $sortie->delete();

    return response()->json(['message' => 'Sortie supprimée et stock mis à jour']);
}

    // Mettre à jour une sortie
   public function updateSortie(Request $request, $id)
{
    $this->autoriserResponsable();

    $sortie = Sortie::findOrFail($id);

    // ⚠️ Sauvegarder anciennes valeurs
    $oldProduitId = $sortie->produit_id;
    $oldQuantite = $sortie->quantite;

    // ✅ Valider les données entrantes
    $request->validate([
        'produit_id' => 'sometimes|exists:products,id',
        'quantite' => 'sometimes|integer|min:1',
        'date' => 'sometimes|date',
        'commentaire' => 'nullable|string',
        'destination' => 'nullable|string'
    ]);

    // 🔁 Obtenir les nouvelles valeurs
    $newProduitId = $request->produit_id ?? $oldProduitId;
    $newQuantite = $request->quantite ?? $oldQuantite;

    // 📦 Si on a changé de produit
    if ($oldProduitId != $newProduitId) {
        $oldProduct = Product::findOrFail($oldProduitId);
        $newProduct = Product::findOrFail($newProduitId);

        // 🔄 Remettre l’ancienne quantité à l’ancien produit
        $oldProduct->stock += $oldQuantite;
        $oldProduct->save();

        // ✅ Vérifier stock du nouveau produit
        if ($newProduct->stock < $newQuantite) {
            return response()->json(['message' => 'Stock insuffisant pour le nouveau produit'], 400);
        }

        // ➖ Déduire la nouvelle quantité
        $newProduct->stock -= $newQuantite;
        $newProduct->save();

        // 🔁 Mettre à jour la table `stocks`
        $oldStock = Stock::where('produit_id', $oldProduitId)->first();
        if ($oldStock) {
            $oldStock->qteSortie -= $oldQuantite;
            $oldStock->valeurStock = ($oldStock->qteEntree - $oldStock->qteSortie) * $oldProduct->price;
            $oldStock->save();
        }

        $newStock = Stock::where('produit_id', $newProduitId)->first();
        if ($newStock) {
            $newStock->qteSortie += $newQuantite;
            $newStock->valeurStock = ($newStock->qteEntree - $newStock->qteSortie) * $newProduct->price;
            $newStock->save();
        }

    } else {
        // Même produit → calculer la différence
        $product = Product::findOrFail($newProduitId);
        $diff = $newQuantite - $oldQuantite;

        if ($diff > 0 && $product->stock < $diff) {
            return response()->json(['message' => 'Stock insuffisant pour augmenter la sortie'], 400);
        }

        // 🔄 Ajuster le stock
        $product->stock -= $diff;
        $product->save();

        // 🔄 Mettre à jour stock produit
        $stock = Stock::where('produit_id', $newProduitId)->first();
        if ($stock) {
            $stock->qteSortie += $diff;
            $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $product->price;
            $stock->save();
        }
    }

    // ✅ Mettre à jour la sortie
    $sortie->produit_id = $newProduitId;
    $sortie->quantite = $newQuantite;
    $sortie->date = $request->date ?? $sortie->date;
    $sortie->commentaire = $request->commentaire ?? $sortie->commentaire;
    $sortie->destination = $request->destination ?? $sortie->destination;
    $sortie->save();

    return response()->json([
        'message' => 'Sortie mise à jour avec succès, stock et données synchronisées',
        'sortie' => $sortie
    ]);
}*/








    // Ajouter un produit
    /*public function ajouterProduit(Request $request)
    {
        $this->autoriserResponsable();

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
    $this->autoriserResponsable();

    $produit = Product::findOrFail($id);

    /* 🚫 Vérifier qu’il n’a pas d’entrées ou de sorties liées
    $hasEntrees = Entree::where('produit_id', $id)->exists();
    $hasSorties = Sortie::where('produit_id', $id)->exists();

    if ($hasEntrees || $hasSorties) {
        return response()->json([
            'message' => 'Impossible de supprimer ce produit car il a des entrées ou des sorties associées.'
        ], 400);
    }*/
/*
    // ✅ Supprimer le stock lié s’il existe
    Stock::where('produit_id', $id)->delete();

    // ✅ Supprimer le produit
    $produit->delete();

    return response()->json(['message' => 'Produit supprimé avec succès']);
}

    // Mettre à jour un produit
   public function updateProduit(Request $request, $id) 
{
    $this->autoriserResponsable();

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
}*/








    // 📋 Afficher toutes les TVA
   /* public function index()
    {
        $this->autoriserResponsable();
        return TVA::all();
    }

    // ➕ Ajouter une TVA
    public function store(Request $request)
    {
        $this->autoriserResponsable();

        $request->validate([
            'nom' => 'required|string|max:255',
            'taux' => 'required|numeric|min:0|max:100'
        ]);

        $tva = TVA::create([
            'nom' => $request->nom,
            'taux' => $request->taux
        ]);

        return response()->json($tva, 201);
    }

    // 🖊 Modifier une TVA et mettre à jour les produits liés
public function update(Request $request, $id)
{
    $this->autoriserResponsable();

    $request->validate([
        'nom' => 'sometimes|string|max:255',
        'taux' => 'required|numeric|min:0|max:100'
    ]);

    $tva = TVA::findOrFail($id);
    $tva->taux = $request->taux;
    $tva->nom = $request->nom;
    
    $tva->save();

    // 🔁 Mise à jour des produits liés (si nécessaire)
    $produits = $tva->produits; // via la relation hasMany

    foreach ($produits as $produit) {
        // ⚠️ Si tu veux juste que le taux soit mis à jour côté TVA, rien à faire ici
        // Mais si tu stockes un champ "prix_ttc" ou autre dépendant de la TVA → tu peux recalculer
        $produit->save();
    }

    return response()->json(['message' => 'TVA mise à jour et produits liés rafraîchis', 'tva' => $tva], 200);
}


    // ❌ Supprimer une TVA
    public function destroy($id)
    {
        $this->autoriserResponsable();

        $tva = TVA::findOrFail($id);
        $tva->delete();

        return response()->json(['message' => 'TVA supprimée'], 200);
    }*/

}




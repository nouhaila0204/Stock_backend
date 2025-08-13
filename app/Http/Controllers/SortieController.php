<?php

namespace App\Http\Controllers;

use App\Models\Sortie;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Entree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;

class SortieController extends Controller
{
    public function indexSortie()
    {
        return Sortie::with('produit')->get();
    }

    public function ajouterSortie(Request $request)
    {
        $request->validate([
            'produit_id' => 'required|exists:products,id',
            'destination' => 'required|string|max:255',
            'commentaire' => 'nullable|string|max:500',
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
            'commentaire' => $request->commentaire,
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
        }

        // Ajouter la quantité sortie
        $stock->qteSortie += $request->quantite;

        // 🔧 CORRECTION: Garder valeurStock comme valeur monétaire
        // Mais s'assurer que le calcul de quantité est cohérent
        $quantiteStock = $stock->qteEntree - $stock->qteSortie;
        $stock->valeurStock = $quantiteStock * $product->price;
        
        // ✅ SYNCHRONISATION: Le stock produit doit correspondre à la quantité réelle
        $product->stock = $quantiteStock; // Quantité, pas valeur monétaire
        $product->save();
        
        $stock->save();

        return response()->json([
            'message' => 'Sortie enregistrée',
            'data' => $sortie
        ], 201);
    }

    // Supprimer une sortie
    public function suppSortie($id)
    {
        $sortie = Sortie::findOrFail($id);

        // 🔁 Remettre la quantité dans le stock du produit
        $product = Product::find($sortie->produit_id);
        $product->stock += $sortie->quantite;

        // 🔁 Mise à jour dans la table `stocks`
        $stock = Stock::where('produit_id', $sortie->produit_id)->first();
        if ($stock) {
            $stock->qteSortie -= $sortie->quantite;
            
            // 🔧 CORRECTION: Calculer la quantité réelle puis la valeur
            $quantiteStock = $stock->qteEntree - $stock->qteSortie;
            $stock->valeurStock = $quantiteStock * $product->price;
            
            // ✅ SYNCHRONISATION: Le stock produit = quantité réelle
            $product->stock = $quantiteStock;
            
            $stock->save();
        }
        
        $product->save();

        // 🗑️ Supprimer la sortie
        $sortie->delete();

        return response()->json(['message' => 'Sortie supprimée et stock mis à jour']);
    }

    // Mettre à jour une sortie
    public function updateSortie(Request $request, $id)
    {
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

            // 🔄 Remettre l'ancienne quantité à l'ancien produit
            $oldProduct->stock += $oldQuantite;

            // ✅ Vérifier stock du nouveau produit
            if ($newProduct->stock < $newQuantite) {
                return response()->json(['message' => 'Stock insuffisant pour le nouveau produit'], 400);
            }

            // ➖ Déduire la nouvelle quantité
            $newProduct->stock -= $newQuantite;

            // 🔁 Mettre à jour la table `stocks` - ANCIEN PRODUIT
            $oldStock = Stock::where('produit_id', $oldProduitId)->first();
            if ($oldStock) {
                $oldStock->qteSortie -= $oldQuantite;
                $oldQuantiteStock = $oldStock->qteEntree - $oldStock->qteSortie;
                $oldStock->valeurStock = $oldQuantiteStock * $oldProduct->price;
                
                // ✅ SYNCHRONISATION
                $oldProduct->stock = $oldQuantiteStock;
                $oldStock->save();
            }
            $oldProduct->save();

            // 🔁 Mettre à jour la table `stocks` - NOUVEAU PRODUIT
            $newStock = Stock::where('produit_id', $newProduitId)->first();
            if ($newStock) {
                $newStock->qteSortie += $newQuantite;
                $newQuantiteStock = $newStock->qteEntree - $newStock->qteSortie;
                $newStock->valeurStock = $newQuantiteStock * $newProduct->price;
                
                // ✅ SYNCHRONISATION
                $newProduct->stock = $newQuantiteStock;
                $newStock->save();
            }
            $newProduct->save();

        } else {
            // Même produit → calculer la différence
            $product = Product::findOrFail($newProduitId);
            $diff = $newQuantite - $oldQuantite;

            if ($diff > 0 && $product->stock < $diff) {
                return response()->json(['message' => 'Stock insuffisant pour augmenter la sortie'], 400);
            }

            // 🔄 Ajuster le stock
            $product->stock -= $diff;

            // 🔄 Mettre à jour stock
            $stock = Stock::where('produit_id', $newProduitId)->first();
            if ($stock) {
                $stock->qteSortie += $diff;
                $quantiteStock = $stock->qteEntree - $stock->qteSortie;
                $stock->valeurStock = $quantiteStock * $product->price;
                
                // ✅ SYNCHRONISATION
                $product->stock = $quantiteStock;
                $stock->save();
            }
            $product->save();
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
    }



    public function showSortie($id)
    {
        $sortie = Sortie::with('produit')->findOrFail($id);
        return response()->json($sortie);
    }

    public function filtrer(Request $request)
    {
        $query = Sortie::with('produit');

        // Filtrer par nom du produit
        if ($request->has('produit_nom') && $request->produit_nom !== null) {
            $query->whereHas('produit', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->produit_nom . '%');
            });
        }
        return response()->json($query->get());
    }

    public function search(Request $request)
    {
        $query = Sortie::query();

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('nom')) {
            $query->where('nom', 'like', '%' . $request->nom . '%');
        }

        if ($request->has('produit_id')) {
            $query->where('produit_id', $request->produit_id);
        }
        if ($request->has('destination')) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }
        return response()->json($query->get());
    }

    public function imprimerBonSortie($id)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->isResponsableStock()) {
                return response()->json(['error' => 'Non autorisé'], 403);
            }

            $sortie = Sortie::with(['produit'])->findOrFail($id);

            $responsablestock = $user->name ?? 'Responsable inconnu';

            $pdf = Pdf::loadView('pdf.bon_sortie', [
                'sortie' => $sortie,
                'responsablestock' => $responsablestock,
            ]);

            return $pdf->stream('bon_sortie_'.$id.'.pdf');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la génération du Bon de sortie',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function afficherBonSortie($id)
    {
        $sortie = Sortie::with(['produit'])->findOrFail($id);

        // Récupérer le nom du responsable connecté
        $responsablestock = Auth::user()->name . ' ' . Auth::user()->prenom;

        return view('pdf.bon_sortie', compact('sortie', 'responsablestock'));
    }
}








/*namespace App\Http\Controllers;

use App\Models\Sortie;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Entree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;

class SortieController extends Controller
{
    public function indexSortie()
    {
        return Sortie::with('produit')->get();
    }

     public function ajouterSortie(Request $request)
    {

        $request->validate([
            'produit_id' => 'required|exists:products,id',
            'destination' => 'required|string|max:255',
            'commentaire' => 'nullable|string|max:500',
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
            'commentaire' => $request->commentaire,
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
    $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $product->price;

    $stock->save();

        return response()->json([
            'message' => 'Sortie enregistrée',
            'data' => $sortie
        ], 201);
    }


    // Supprimer une sortie
    public function suppSortie($id)
{

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
}

    public function showSortie($id)
    {
        $sortie = Sortie::with('produit')->findOrFail($id);
        return response()->json($sortie);
    }

    public function filtrer(Request $request)
{
    $query = Sortie::with('produit');

    // 🔍 Filtrer par nom du produit
    if ($request->has('produit_nom') && $request->produit_nom !== null) {
        $query->whereHas('produit', function ($q) use ($request) {
            $q->where('name', 'like', '%' . $request->produit_nom . '%');
        });
    }
    return response()->json($query->get());
}


    public function search(Request $request)
{
    $query = Sortie::query();

    if ($request->has('date')) {
        $query->whereDate('date', $request->date);
    }

    if ($request->has('nom')) {
        $query->where('nom', 'like', '%' . $request->nom . '%');
    }

    if ($request->has('produit_id')) {
        $query->where('produit_id', $request->produit_id);
    }
    if ($request->has('destination')) {
        $query->where('destination', 'like', '%' . $request->destination . '%');
    }
    return response()->json($query->get());
}



    public function imprimerBonSortie($id)
{
    try {
        $user = Auth::user();
        if (!$user || !$user->isResponsableStock()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $sortie = Sortie::with(['produit'])->findOrFail($id);

        $responsablestock = $user->name ?? 'Responsable inconnu';

        $pdf = Pdf::loadView('pdf.bon_sortie', [
            'sortie' => $sortie,
            'responsablestock' => $responsablestock,
        ]);

        return $pdf->stream('bon_sortie_'.$id.'.pdf');

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Erreur lors de la génération du Bon de sortie',
            'message' => $e->getMessage()
        ], 500);
    }
}

public function afficherBonSortie($id)
{
    $sortie = Sortie::with(['produit'])->findOrFail($id);

    // Récupérer le nom du responsable connecté
    $responsablestock = Auth::user()->name . ' ' . Auth::user()->prenom;

    return view('pdf.bon_sortie', compact('sortie', 'responsablestock'));
}

}*/

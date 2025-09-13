<?php

namespace App\Http\Controllers;

use App\Models\Sortie;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Entree;
use App\Models\Alerte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;
use Illuminate\Support\Facades\DB; 

class SortieController extends Controller
{
    public function indexSortie()
    {
        return Sortie::with(['produits.tva'])->get();
    }

       // Ajouter une sortie
    public function ajouterSortie(Request $request)
    {
        $request->validate([
            'destination' => 'required|string|max:255',
            'commentaire' => 'nullable|string|max:500',
            'date' => 'required|date',
            'produits' => 'required|array|min:1',
            'produits.*.produit_id' => 'required|exists:products,id',
            'produits.*.quantite' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $sortie = Sortie::create([
                'destination' => $request->destination,
                'commentaire' => $request->commentaire,
                'date' => $request->date,
            ]);

            foreach ($request->produits as $produitData) {
                $product = Product::findOrFail($produitData['produit_id']);
                $quantiteDemandee = $produitData['quantite'];

                // Vérifier la quantité en stock
                if ($product->stock < $quantiteDemandee) {
                    DB::rollBack();
                    return response()->json(['message' => "Quantité insuffisante pour le produit {$product->name}"], 400);
                }

                // Appliquer la méthode FIFO
                $lots = $product->entrees()
                    ->wherePivot('quantite_restante', '>', 0)
                    ->orderBy('entree_product.created_at', 'asc')
                    ->get();

                $quantiteRestanteASortir = $quantiteDemandee;
                foreach ($lots as $lot) {
                    if ($quantiteRestanteASortir <= 0) {
                        break;
                    }

                    $quantiteDisponible = $lot->pivot->quantite_restante;
                    $quantiteASortir = min($quantiteDisponible, $quantiteRestanteASortir);

                    // Mettre à jour quantite_restante dans entree_product
                    $lot->pivot->quantite_restante -= $quantiteASortir;
                    $lot->pivot->save();

                    $quantiteRestanteASortir -= $quantiteASortir;
                }

                if ($quantiteRestanteASortir > 0) {
                    DB::rollBack();
                    return response()->json(['message' => "Impossible de sortir {$quantiteDemandee} unités du produit {$product->name}: stock insuffisant dans les lots"], 400);
                }

                // Attacher le produit à la sortie
                $sortie->produits()->attach($produitData['produit_id'], [
                    'quantite' => $quantiteDemandee,
                ]);

                // Mettre à jour le stock du produit
                $product->stock -= $quantiteDemandee;
                $product->save();

                // Créer une alerte si nécessaire
                if ($product->stock < $product->stock_min) {
                    Alerte::updateOrCreate(
                        ['produit_id' => $product->id],
                        ['date' => now(), 'is_viewed' => false]
                    );
                }

                // Mettre à jour la table stocks
                $stock = Stock::where('produit_id', $produitData['produit_id'])->first();
                if (!$stock) {
                    $stock = new Stock();
                    $stock->produit_id = $produitData['produit_id'];
                    $stock->qteEntree = 0;
                    $stock->qteSortie = 0;
                    $stock->valeurStock = 0;
                }
                $stock->qteSortie += $quantiteDemandee;
                $quantiteStock = $stock->qteEntree - $stock->qteSortie;
                $stock->valeurStock = $product->calculerValeurStock();
                $stock->save();

                // Synchroniser le stock
                $product->stock = $quantiteStock;
                $product->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Sortie enregistrée et stock mis à jour',
                'data' => $sortie->load(['produits.tva', 'user', 'employe']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de l\'ajout de la sortie : ' . $e->getMessage()], 500);
        }
    }

    // Supprimer une sortie
    public function suppSortie($id)
    {
        $sortie = Sortie::with('produits')->findOrFail($id);

        DB::beginTransaction();
        try {
            foreach ($sortie->produits as $produit) {
                $product = Product::findOrFail($produit->id);
                $quantite = $produit->pivot->quantite;

                // Restaurer le stock dans les lots (FIFO inversé : dernier sorti, premier rentré)
                $lots = $product->entrees()
                    ->wherePivot('quantite_restante', '>=', 0)
                    ->orderBy('entree_product.created_at', 'desc')
                    ->get();

                $quantiteARestaurer = $quantite;
                foreach ($lots as $lot) {
                    if ($quantiteARestaurer <= 0) {
                        break;
                    }

                    $quantiteDisponible = $lot->pivot->quantite - $lot->pivot->quantite_restante;
                    $quantiteARestaurerPourCeLot = min($quantiteARestaurer, $quantiteDisponible);

                    $lot->pivot->quantite_restante += $quantiteARestaurerPourCeLot;
                    $lot->pivot->save();

                    $quantiteARestaurer -= $quantiteARestaurerPourCeLot;
                }

                // Restaurer le stock du produit
                $product->stock += $quantite;
                $product->save();

                // Supprimer l'alerte si nécessaire
                if ($product->stock >= $product->stock_min) {
                    Alerte::where('produit_id', $product->id)->delete();
                }

                // Mettre à jour la table stocks
                $stock = Stock::where('produit_id', $produit->id)->first();
                if ($stock) {
                    $stock->qteSortie -= $quantite;
                    $quantiteStock = $stock->qteEntree - $stock->qteSortie;
                    $stock->valeurStock = $product->calculerValeurStock();
                    $stock->save();

                    // Synchroniser le stock
                    $product->stock = $quantiteStock;
                    $product->save();
                }
            }

            // Détacher les produits et supprimer la sortie
            $sortie->produits()->detach();
            $sortie->delete();

            DB::commit();
            return response()->json(['message' => 'Sortie supprimée et stock mis à jour']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la suppression : ' . $e->getMessage()], 500);
        }
    }

    // Mettre à jour une sortie
    public function updateSortie(Request $request, $id)
    {
        $sortie = Sortie::with('produits')->findOrFail($id);

        $request->validate([
            'destination' => 'sometimes|string|max:255',
            'commentaire' => 'nullable|string|max:500',
            'date' => 'sometimes|date',
            'produits' => 'sometimes|array|min:1',
            'produits.*.produit_id' => 'required|exists:products,id',
            'produits.*.quantite' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Mettre à jour les champs principaux
            $sortie->update([
                'destination' => $request->destination ?? $sortie->destination,
                'commentaire' => $request->commentaire ?? $sortie->commentaire,
                'date' => $request->date ?? $sortie->date,
            ]);

            // Si les produits sont fournis, mettre à jour la table pivot
            if ($request->has('produits')) {
                // Restaurer les stocks pour les anciens produits
                foreach ($sortie->produits as $produit) {
                    $product = Product::findOrFail($produit->id);
                    $quantite = $produit->pivot->quantite;

                    // Restaurer le stock dans les lots (FIFO inversé)
                    $lots = $product->entrees()
                        ->wherePivot('quantite_restante', '>=', 0)
                        ->orderBy('entree_product.created_at', 'desc')
                        ->get();

                    $quantiteARestaurer = $quantite;
                    foreach ($lots as $lot) {
                        if ($quantiteARestaurer <= 0) {
                            break;
                        }

                        $quantiteDisponible = $lot->pivot->quantite - $lot->pivot->quantite_restante;
                        $quantiteARestaurerPourCeLot = min($quantiteARestaurer, $quantiteDisponible);

                        $lot->pivot->quantite_restante += $quantiteARestaurerPourCeLot;
                        $lot->pivot->save();

                        $quantiteARestaurer -= $quantiteARestaurerPourCeLot;
                    }

                    // Restaurer le stock du produit
                    $product->stock += $quantite;
                    $product->save();

                    // Supprimer l'alerte si nécessaire
                    if ($product->stock >= $product->stock_min) {
                        Alerte::where('produit_id', $product->id)->delete();
                    }

                    // Mettre à jour la table stocks
                    $stock = Stock::where('produit_id', $produit->id)->first();
                    if ($stock) {
                        $stock->qteSortie -= $quantite;
                        $quantiteStock = $stock->qteEntree - $stock->qteSortie;
                        $stock->valeurStock = $product->calculerValeurStock();
                        $stock->save();

                        // Synchroniser le stock
                        $product->stock = $quantiteStock;
                        $product->save();
                    }
                }

                // Détacher les anciens produits
                $sortie->produits()->detach();

                // Attacher les nouveaux produits
                foreach ($request->produits as $produitData) {
                    $product = Product::findOrFail($produitData['produit_id']);
                    $quantiteDemandee = $produitData['quantite'];

                    // Vérifier la quantité en stock
                    if ($product->stock < $quantiteDemandee) {
                        DB::rollBack();
                        return response()->json(['message' => "Quantité insuffisante pour le produit {$product->name}"], 400);
                    }

                    // Appliquer la méthode FIFO
                    $lots = $product->entrees()
                        ->wherePivot('quantite_restante', '>', 0)
                        ->orderBy('entree_product.created_at', 'asc')
                        ->get();

                    $quantiteRestanteASortir = $quantiteDemandee;
                    foreach ($lots as $lot) {
                        if ($quantiteRestanteASortir <= 0) {
                            break;
                        }

                        $quantiteDisponible = $lot->pivot->quantite_restante;
                        $quantiteASortir = min($quantiteDisponible, $quantiteRestanteASortir);

                        $lot->pivot->quantite_restante -= $quantiteASortir;
                        $lot->pivot->save();

                        $quantiteRestanteASortir -= $quantiteASortir;
                    }

                    if ($quantiteRestanteASortir > 0) {
                        DB::rollBack();
                        return response()->json(['message' => "Impossible de sortir {$quantiteDemandee} unités du produit {$product->name}: stock insuffisant dans les lots"], 400);
                    }

                    // Attacher le produit
                    $sortie->produits()->attach($produitData['produit_id'], [
                        'quantite' => $quantiteDemandee,
                    ]);

                    // Mettre à jour le stock
                    $product->stock -= $quantiteDemandee;
                    $product->save();

                    // Créer une alerte si nécessaire
                    if ($product->stock < $product->stock_min) {
                        Alerte::updateOrCreate(
                            ['produit_id' => $product->id],
                            ['date' => now(), 'is_viewed' => false]
                        );
                    }

                    // Mettre à jour la table stocks
                    $stock = Stock::where('produit_id', $produitData['produit_id'])->first();
                    if (!$stock) {
                        $stock = new Stock();
                        $stock->produit_id = $produitData['produit_id'];
                        $stock->qteEntree = 0;
                        $stock->qteSortie = 0;
                        $stock->valeurStock = 0;
                    }
                    $stock->qteSortie += $quantiteDemandee;
                    $quantiteStock = $stock->qteEntree - $stock->qteSortie;
                    $stock->valeurStock = $product->calculerValeurStock();
                    $stock->save();

                    // Synchroniser le stock
                    $product->stock = $quantiteStock;
                    $product->save();
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Sortie mise à jour avec succès, stock et données synchronisées',
                'data' => $sortie->load(['produits.tva', 'user', 'employe']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()], 500);
        }
    }


 // Afficher une sortie
    public function showSortie($id)
    {
        $sortie = Sortie::with(['produits.tva'])->findOrFail($id);
        return response()->json($sortie);
    }

    // Filtrer les sorties
    public function filtrer(Request $request)
    {
        $query = Sortie::with(['produits.tva']);

        if ($request->has('produit_nom') && $request->produit_nom !== null) {
            $query->whereHas('produits', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->produit_nom . '%');
            });
        }

        return response()->json($query->get());
    }


     // Rechercher les sorties
    public function search(Request $request)
    {
        $query = Sortie::with(['produits.tva']);

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }
        
        if ($request->has('nom')) {
            $query->where('nom', 'like', '%' . $request->nom . '%');
        }

        if ($request->has('destination')) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }

        if ($request->has('produit_id')) {
            $query->whereHas('produits', function ($q) use ($request) {
                $q->where('produit_id', $request->produit_id);
            });
        }

        return response()->json($query->get());
    }

 // Imprimer le bon de sortie
    public function imprimerBonSortie($id)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->isResponsableStock()) {
                return response()->json(['error' => 'Non autorisé'], 403);
            }

            $sortie = Sortie::with(['produits.tva'])->findOrFail($id);
            $responsablestock = $user->name . ' ' . $user->prenom;

            $pdf = Pdf::loadView('pdf.bon_sortie', [
                'sortie' => $sortie,
                'responsablestock' => $responsablestock,
            ]);

            return $pdf->stream('bon_sortie_' . $id . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la génération du Bon de sortie',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Afficher le bon de sortie
    public function afficherBonSortie($id)
    {
        $sortie = Sortie::with(['produits.tva'])->findOrFail($id);
        $responsablestock = Auth::user()->name . ' ' . Auth::user()->prenom;

        return view('pdf.bon_sortie', compact('sortie', 'responsablestock'));
    }
}
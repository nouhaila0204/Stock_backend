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
        $entrees = Entree::with(['produit.tva', 'fournisseur'])->latest()->get()->map(function ($entree) {
            $total_ht = $entree->quantite * $entree->prixUnitaire;
            $tvaRate = $entree->produit->tva->taux ?? 0;
            $total_ttc = $total_ht * (1 + $tvaRate / 100);
            
            return [
                'id' => $entree->id,
                'numBond' => $entree->numBond,
                'codeMarche' => $entree->codeMarche,
                'date' => $entree->date,
                'fournisseur_id' => $entree->fournisseur_id,
                'produit_id' => $entree->produit_id,
                'quantite' => $entree->quantite,
                'prixUnitaire' => $entree->prixUnitaire,
                'total_ht' => $total_ht,
                'total_ttc' => $total_ttc,
                'produit' => $entree->produit ? [
                    'id' => $entree->produit->id,
                    'name' => $entree->produit->name,
                    'reference' => $entree->produit->reference,
                    'tva' => $entree->produit->tva ? [
                        'id' => $entree->produit->tva->id,
                        'taux' => $entree->produit->tva->taux,
                    ] : null,
                ] : null,
                'fournisseur' => $entree->fournisseur ? [
                    'id' => $entree->fournisseur->id,
                    'raisonSocial' => $entree->fournisseur->raisonSocial,
                ] : null,
            ];
        });
        
        return response()->json($entrees);
    }

    public function show($id)
    {
        try {
            $entree = Entree::with(['produit.tva', 'fournisseur'])->findOrFail($id);

            // Calculer total_ht et total_ttc
            $entree->total_ht = $entree->quantite * $entree->prixUnitaire;
            $entree->total_ttc = $entree->total_ht * (1 + ($entree->produit->tva->taux / 100));

            return response()->json($entree, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Entrée non trouvée'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }


    // ➕ Ajouter une entrée de stock
    public function ajouterEntree(Request $request)
    {
        $request->validate([
            'produit_id' => 'required|exists:products,id',
            'numBond' => 'required|string|max:255|unique:entrees,numBond',
            'codeMarche' => 'nullable|string',
            'prixUnitaire' => 'required|numeric|min:0',
            'quantite' => 'required|integer|min:1',
            'date' => 'required|date',
            'fournisseur_id' => 'required|exists:fournisseurs,id',
        ]);

        $entree = Entree::create([
            'produit_id' => $request->produit_id,
            'prixUnitaire' => $request->prixUnitaire,
            'quantite' => $request->quantite,
            'numBond' => $request->numBond,
            'codeMarche' => $request->codeMarche,
            'date' => $request->date,
            'fournisseur_id' => $request->fournisseur_id,
        ]);

        // Mettre à jour la table stocks
        $stock = Stock::where('produit_id', $request->produit_id)->first();

        if (!$stock) {
            $stock = new Stock();
            $stock->produit_id = $request->produit_id;
            $stock->qteEntree = 0;
            $stock->qteSortie = 0;
            $stock->valeurStock = 0;
        }

        $stock->qteEntree += $request->quantite;
        $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * $request->prixUnitaire;
        $stock->save();

        // Mise à jour du stock du produit
        $product = Product::find($request->produit_id);
        $product->stock += $request->quantite;
        $product->save();

        return response()->json([
            'message' => 'Entrée enregistrée et stock mis à jour',
            'data' => $entree->load(['produit.tva', 'fournisseur']),
        ], 201);
    } 

    // Supprimer une entrée
    public function suppEntree($id)
    {
        $entree = Entree::findOrFail($id);

        // 🔁 Mise à jour du stock dans la table `products`
        $product = Product::find($entree->produit_id);
        if ($product->stock < $entree->quantite) {
            return response()->json(['message' => 'Stock insuffisant pour supprimer cette entrée'], 400);
        }
        $product->stock -= $entree->quantite;
        $product->save();

        // 🔁 Mise à jour du stock dans la table `stocks`
        $stock = Stock::where('produit_id', $entree->produit_id)->first();
        if ($stock) {
            $stock->qteEntree -= $entree->quantite;
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
            'numBond' => 'sometimes|string|max:255|unique:entrees,numBond,' . $id,
            'prixUnitaire' => 'sometimes|numeric|min:0',
            'quantite' => 'sometimes|integer|min:1',
            'date' => 'sometimes|date',
            'fournisseur_id' => 'sometimes|exists:fournisseurs,id',
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

        // ✅ Mettre à jour stock global (table `stocks`)
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

            $stock->valeurStock = ($stock->qteEntree - $stock->qteSortie) * ($request->prixUnitaire ?? $entree->prixUnitaire);
            $stock->save();
        }

        return response()->json([
            'message' => 'Entrée mise à jour avec ajustement du stock',
            'data' => $entree->load(['produit.tva', 'fournisseur']),
        ]);
    }

    // 🔍 Filtrer les entrées
    public function filtrer(Request $request)
    {
        $query = Entree::with(['produit.tva', 'fournisseur']);

        // Filtrer par nom du produit
        if ($request->has('produit_nom') && $request->produit_nom !== null) {
            $query->whereHas('produit', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->produit_nom . '%');
            });
        }

        // Filtrer par numéro de bon
        if ($request->filled('numBond')) {
            $query->where('numBond', 'like', '%' . $request->numBond . '%');
        }

        // Filtrer par code marché
        if ($request->filled('codeMarche')) {
            $query->where('codeMarche', 'like', '%' . $request->codeMarche . '%');
        }

        // Filtrer par fournisseur (raisonSocial)
        if ($request->filled('fournisseur')) {
            $query->whereHas('fournisseur', function ($q) use ($request) {
                $q->where('raisonSocial', 'like', '%' . $request->fournisseur . '%');
            });
        }

        // Filtrer par date
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        $entrees = $query->get()->map(function ($entree) {
            $total_ht = $entree->quantite * $entree->prixUnitaire;
            $tvaRate = $entree->produit->tva->taux ?? 0;
            $total_ttc = $total_ht * (1 + $tvaRate / 100);
            
            return [
                'id' => $entree->id,
                'numBond' => $entree->numBond,
                'codeMarche' => $entree->codeMarche,
                'date' => $entree->date,
                'fournisseur_id' => $entree->fournisseur_id,
                'produit_id' => $entree->produit_id,
                'quantite' => $entree->quantite,
                'prixUnitaire' => $entree->prixUnitaire,
                'total_ht' => $total_ht,
                'total_ttc' => $total_ttc,
                'produit' => $entree->produit ? [
                    'id' => $entree->produit->id,
                    'name' => $entree->produit->name,
                    'reference' => $entree->produit->reference,
                    'tva' => $entree->produit->tva ? [
                        'id' => $entree->produit->tva->id,
                        'taux' => $entree->produit->tva->taux,
                    ] : null,
                ] : null,
                'fournisseur' => $entree->fournisseur ? [
                    'id' => $entree->fournisseur->id,
                    'raisonSocial' => $entree->fournisseur->raisonSocial,
                ] : null,
            ];
        });

        return response()->json($entrees, 200);
    }

    public function afficherBonEntree($id)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !$user->isResponsableStock()) {
            return response()->json(['error' => 'Utilisateur non autorisé'], 403);
        }

        $entree = Entree::with(['produit.tva', 'fournisseur'])->findOrFail($id);
        $responsablestock = $user->name;

        $html = view('pdf.bon_entree', compact('entree', 'responsablestock'))->render();
        return response($html, 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    public function imprimerBon($id)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !$user->isResponsableStock()) {
                return response()->json(['error' => 'Utilisateur non autorisé'], 403);
            }

            $entree = Entree::with(['produit.tva', 'fournisseur'])->findOrFail($id);

            if (!$entree->produit) {
                return response()->json(['error' => 'Aucun produit trouvé pour cette entrée'], 404);
            }

            $responsablestock = $user->name;

            $pdf = Pdf::loadView('pdf.bon_entree', [
                'entree' => $entree,
                'responsablestock' => $responsablestock,
            ]);

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="bon_entree_' . $id . '.pdf"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la génération du PDF',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
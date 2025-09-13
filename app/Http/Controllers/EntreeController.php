<?php

namespace App\Http\Controllers;

use App\Models\Entree;
use App\Models\Alerte;
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
        $entrees = Entree::with(['produits.tva', 'fournisseur'])->latest()->get()->map(function ($entree) {
            $produits = $entree->produits->map(function ($produit) {
                $total_ht = $produit->pivot->quantite * $produit->pivot->prixUnitaire;
                $tvaRate = $produit->tva->taux ?? 0;
                $total_ttc = $total_ht * (1 + $tvaRate / 100);

                return [
                    'id' => $produit->id,
                    'name' => $produit->name,
                    'reference' => $produit->reference,
                    'quantite' => $produit->pivot->quantite,
                    'prixUnitaire' => $produit->pivot->prixUnitaire,
                    'total_ht' => $total_ht,
                    'total_ttc' => $total_ttc,
                    'tva' => $produit->tva ? [
                        'id' => $produit->tva->id,
                        'taux' => $produit->tva->taux,
                    ] : null,
                ];
            });

            return [
                'id' => $entree->id,
                'numBond' => $entree->numBond,
                'codeMarche' => $entree->codeMarche,
                'date' => $entree->date,
                'fournisseur_id' => $entree->fournisseur_id,
                'produits' => $produits,
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
            $entree = Entree::with(['produits.tva', 'fournisseur'])->findOrFail($id);

            $produits = $entree->produits->map(function ($produit) {
                $total_ht = $produit->pivot->quantite * $produit->pivot->prixUnitaire;
                $tvaRate = $produit->tva->taux ?? 0;
                $total_ttc = $total_ht * (1 + $tvaRate / 100);

                return [
                    'id' => $produit->id,
                    'name' => $produit->name,
                    'reference' => $produit->reference,
                    'quantite' => $produit->pivot->quantite,
                    'prixUnitaire' => $produit->pivot->prixUnitaire,
                    'total_ht' => $total_ht,
                    'total_ttc' => $total_ttc,
                    'tva' => $produit->tva ? [
                        'id' => $produit->tva->id,
                        'taux' => $produit->tva->taux,
                    ] : null,
                ];
            });

            return response()->json([
                'id' => $entree->id,
                'numBond' => $entree->numBond,
                'codeMarche' => $entree->codeMarche,
                'date' => $entree->date,
                'fournisseur_id' => $entree->fournisseur_id,
                'produits' => $produits,
                'fournisseur' => $entree->fournisseur ? [
                    'id' => $entree->fournisseur->id,
                    'raisonSocial' => $entree->fournisseur->raisonSocial,
                ] : null,
            ], 200);
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
            'numBond' => 'required|string|max:255|unique:entrees,numBond',
            'codeMarche' => 'nullable|string',
            'date' => 'required|date',
            'fournisseur_id' => 'required|exists:fournisseurs,id',
            'produits' => 'required|array|min:1',
            'produits.*.produit_id' => 'required|exists:products,id',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prixUnitaire' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $entree = Entree::create([
                'numBond' => $request->numBond,
                'codeMarche' => $request->codeMarche,
                'date' => $request->date,
                'fournisseur_id' => $request->fournisseur_id,
            ]);

            foreach ($request->produits as $produitData) {
                // Ajout du lot avec quantite_restante = quantite
                $entree->produits()->attach($produitData['produit_id'], [
                    'quantite' => $produitData['quantite'],
                    'prixUnitaire' => $produitData['prixUnitaire'],
                    'quantite_restante' => $produitData['quantite'],
                ]);

                // Mise à jour du stock global du produit
                $product = Product::find($produitData['produit_id']);
                $product->stock += $produitData['quantite'];
                $product->save();

                // Vérifier et supprimer l'alerte si stock >= stock_min
                $alerte = Alerte::where('produit_id', $produitData['produit_id'])->first();
                if ($alerte && $product->stock >= $product->stock_min) {
                    $alerte->delete();
                }

                // Mise à jour du Stock global en recalculant via FIFO
                $stock = Stock::firstOrNew(['produit_id' => $produitData['produit_id']]);
                $stock->qteEntree += $produitData['quantite'];
                $stock->valeurStock = $product->calculerValeurStock(); // méthode FIFO dans Product.php
                $stock->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Entrée enregistrée et stock mis à jour (FIFO)',
                'data' => $entree->load(['produits.tva', 'fournisseur']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de l\'ajout de l\'entrée : ' . $e->getMessage()], 500);
        }
    }

    // ❌ Supprimer une entrée
    public function suppEntree($id)
    {
        $entree = Entree::findOrFail($id);

        DB::beginTransaction();
        try {
            foreach ($entree->produits as $produit) {
                $product = Product::find($produit->id);

                // Vérif stock
                if ($product->stock < $produit->pivot->quantite) {
                    return response()->json(['message' => 'Stock insuffisant pour le produit ' . $produit->name], 400);
                }

                // Mise à jour stock global
                $product->stock -= $produit->pivot->quantite;
                $product->save();

                // Suppression du lot
                $produit->pivot->delete();

                // Recalculer valeur du stock
                $stock = Stock::where('produit_id', $produit->id)->first();
                if ($stock) {
                    $stock->qteEntree -= $produit->pivot->quantite;
                    $stock->valeurStock = $product->calculerValeurStock();
                    $stock->save();
                }
            }

            $entree->produits()->detach();
            $entree->delete();

            DB::commit();
            return response()->json(['message' => 'Entrée supprimée et stock recalculé (FIFO)']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la suppression : ' . $e->getMessage()], 500);
        }
    }

    // ✏️ Mettre à jour une entrée
    public function updateEntree(Request $request, $id)
    {
        $entree = Entree::findOrFail($id);

        $request->validate([
            'numBond' => 'sometimes|string|max:255|unique:entrees,numBond,' . $id,
            'codeMarche' => 'sometimes|nullable|string',
            'date' => 'sometimes|date',
            'fournisseur_id' => 'sometimes|exists:fournisseurs,id',
            'produits' => 'sometimes|array|min:1',
            'produits.*.produit_id' => 'sometimes|exists:products,id',
            'produits.*.quantite' => 'sometimes|integer|min:1',
            'produits.*.prixUnitaire' => 'sometimes|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $entree->fill($request->only(['numBond', 'codeMarche', 'date', 'fournisseur_id']));
            $entree->save();

            if ($request->has('produits')) {
                // Annuler anciens lots
                foreach ($entree->produits as $produit) {
                    $product = Product::find($produit->id);
                    $product->stock -= $produit->pivot->quantite;
                    $product->save();

                    $produit->pivot->delete();

                    $stock = Stock::where('produit_id', $produit->id)->first();
                    if ($stock) {
                        $stock->qteEntree -= $produit->pivot->quantite;
                        $stock->valeurStock = $product->calculerValeurStock();
                        $stock->save();
                    }
                }

                $entree->produits()->detach();

                // Ajouter nouveaux lots
                foreach ($request->produits as $produitData) {
                    $entree->produits()->attach($produitData['produit_id'], [
                        'quantite' => $produitData['quantite'],
                        'prixUnitaire' => $produitData['prixUnitaire'],
                        'quantite_restante' => $produitData['quantite'],
                    ]);

                    $product = Product::find($produitData['produit_id']);
                    $product->stock += $produitData['quantite'];
                    $product->save();

                    $stock = Stock::firstOrNew(['produit_id' => $produitData['produit_id']]);
                    $stock->qteEntree += $produitData['quantite'];
                    $stock->valeurStock = $product->calculerValeurStock();
                    $stock->save();
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Entrée mise à jour avec ajustement du stock (FIFO)',
                'data' => $entree->load(['produits.tva', 'fournisseur']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()], 500);
        }
    }



    // 🔍 Filtrer les entrées
   public function filtrer(Request $request)
    {
        $query = Entree::with(['produits.tva', 'fournisseur']);

        if ($request->has('produit_nom') && $request->produit_nom !== null) {
            $query->whereHas('produits', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->produit_nom . '%');
            });
        }

        if ($request->filled('numBond')) {
            $query->where('numBond', 'like', '%' . $request->numBond . '%');
        }

        if ($request->filled('codeMarche')) {
            $query->where('codeMarche', 'like', '%' . $request->codeMarche . '%');
        }

        if ($request->filled('fournisseur')) {
            $query->whereHas('fournisseur', function ($q) use ($request) {
                $q->where('raisonSocial', 'like', '%' . $request->fournisseur . '%');
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        $entrees = $query->get()->map(function ($entree) {
            $produits = $entree->produits->map(function ($produit) {
                $total_ht = $produit->pivot->quantite * $produit->pivot->prixUnitaire;
                $tvaRate = $produit->tva->taux ?? 0;
                $total_ttc = $total_ht * (1 + $tvaRate / 100);

                return [
                    'id' => $produit->id,
                    'name' => $produit->name,
                    'reference' => $produit->reference,
                    'quantite' => $produit->pivot->quantite,
                    'prixUnitaire' => $produit->pivot->prixUnitaire,
                    'total_ht' => $total_ht,
                    'total_ttc' => $total_ttc,
                    'tva' => $produit->tva ? [
                        'id' => $produit->tva->id,
                        'taux' => $produit->tva->taux,
                    ] : null,
                ];
            });

            return [
                'id' => $entree->id,
                'numBond' => $entree->numBond,
                'codeMarche' => $entree->codeMarche,
                'date' => $entree->date,
                'fournisseur_id' => $entree->fournisseur_id,
                'produits' => $produits,
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

        $entree = Entree::with(['produits.tva', 'fournisseur'])->findOrFail($id);
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

            $entree = Entree::with(['produits.tva', 'fournisseur'])->findOrFail($id);

            if ($entree->produits->isEmpty()) {
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
<?php

namespace App\Http\Controllers;

use App\Models\TVA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TVAController extends Controller
{
    // 📋 Afficher toutes les TVA
    public function index()
    {
        
        return TVA::all();
    }

    // 🔍 Afficher une TVA par ID
    public function Show($id)
    {
        $tva = TVA::findOrFail($id);
        return response()->json($tva);
    }

    // ➕ Ajouter une TVA
    public function store(Request $request)
    {


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
    $tva = TVA::findOrFail($id);

    // Vérifier si des produits sont liés
    if ($tva->produits()->count() > 0) {
        return response()->json([
            'message' => 'Impossible de supprimer cette TVA, elle est utilisée par des produits.',
            'error' => true
        ], 400);
    }

    $tva->delete();
    return response()->json(['message' => 'TVA supprimée avec succès'], 200);
}


    // 🔍 Rechercher TVA par mot-clé
    public function rechercher(Request $request)
{
    $query = TVA::query();

    if ($request->has('nom')) {
        $query->where('nom', 'like', '%' . $request->nom . '%');
    }

    if ($request->has('taux')) {
        $query->where('taux', $request->taux);
    }

    return response()->json($query->get());
}
}
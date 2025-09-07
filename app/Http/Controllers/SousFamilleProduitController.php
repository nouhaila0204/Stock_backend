<?php

namespace App\Http\Controllers;

use App\Models\SousFamilleProduit;
use Illuminate\Http\Request;

class SousFamilleProduitController extends Controller
{
    public function index()
    {
        return SousFamilleProduit::with('famille')->get();
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'nom' => 'required|string',
            'description' => 'nullable|string',
            'famille_produit_id' => 'required|exists:famille_produits,id'
        ]);

        $sousFamille = SousFamilleProduit::create($fields);

        return response()->json($sousFamille->load('famille'), 201);
    }

    public function show(SousFamilleProduit $sousFamille)
    {
        return $sousFamille->load('famille');
    }

    public function update(Request $request, SousFamilleProduit $sousFamille)
    {
        $fields = $request->validate([
            'nom' => 'sometimes|string',
            'description' => 'nullable|string',
            'famille_produit_id' => 'sometimes|exists:famille_produits,id',
        ]);

        $sousFamille->update($fields);
        return $sousFamille->load('famille');
    }

    public function destroy(SousFamilleProduit $sousFamille)
    {
        $sousFamille->delete();
        return response()->json(['message' => 'Sous-famille supprimée']);
    }

 // 🔍 Rechercher sous-famille par mot-clé - CORRIGÉ
public function search(Request $request)
{
    $query = SousFamilleProduit::with('famille'); // Précharge la relation famille

    if ($request->has('nom')) {
        $query->where('nom', 'like', '%' . $request->nom . '%');
    }

    if ($request->has('famille_id')) {
        $query->where('famille_produit_id', $request->famille_id);
    }

    $results = $query->get();
    return response()->json($results);
}
}

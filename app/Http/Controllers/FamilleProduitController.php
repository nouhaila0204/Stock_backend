<?php

namespace App\Http\Controllers;

use App\Models\FamilleProduit;
use Illuminate\Http\Request;

class FamilleProduitController extends Controller
{
    public function index()
    {
        return FamilleProduit::all(); // ✅ Corrigé
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string',
            'description' => 'nullable|string'
        ]);

        $famille = FamilleProduit::create([
            'nom' => $request->nom,
            'description' => $request->description
        ]);

        return response()->json($famille, 201);
    }

    public function show(FamilleProduit $famille)
    {
        return $famille;
    }

    public function update(Request $request, FamilleProduit $famille)
    {
        $fields = $request->validate([
            'nom' => 'sometimes|string',
            'description' => 'nullable|string',
        ]);

        $famille->update($fields);
        return $famille;
    }

    public function destroy(FamilleProduit $famille)
    {
        $famille->delete();
        return response()->json(['message' => 'Famille supprimée']);
    }

 // 🔍 Rechercher famille par mot-clé
   public function search(Request $request)
{
    $query = FamilleProduit::query();

    if ($request->has('nom')) {
        $query->where('nom', 'like', '%' . $request->nom . '%');
    }

    return response()->json($query->get());
}

}
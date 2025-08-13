<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use Illuminate\Http\Request;

class AlerteController extends Controller
{
    public function index()
    {
        return Alerte::with('produit')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'produit_id' => 'required|exists:products,id',

            'date' => 'required|date'
        ]);

        return Alerte::create($request->all());
    }

    public function show($id)
    {
        return Alerte::with('produit')->findOrFail($id);
    }

    public function destroy($id)
    {
        $alerte = Alerte::findOrFail($id);
        $alerte->delete();

        return response()->json(['message' => 'Alerte supprimée']);
    }
}


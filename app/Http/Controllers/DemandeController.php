<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemandeController extends Controller
{
    public function index()
    {
        return Demande::with('employe')->get();
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'raison' => 'required|string',
        ]);

        $demande = Demande::create([
            'raison' => $validated['raison'],
            'user_id' => Auth::id(),
        ]);

        return response()->json($demande->load('employe'), 201);
    }

    public function show($id)
    {
        $demande = Demande::findOrFail($id);
        return response()->json($demande);
    }

    public function edit(Demande $demande)
    {
        //
    }

    public function update(Request $request, Demande $demande)
    {
        //
    }

    public function destroy($id)
    {
        $demande = Demande::findOrFail($id);
        $demande->delete();

        return response()->json(['message' => 'Demande supprimée avec succès']);
    }
}
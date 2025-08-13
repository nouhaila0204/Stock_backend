<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organigramme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class OrganigrammeController extends Controller{
/*{
    public function ajouter(Request $request)
    {
        $request->validate([
            'nom' => 'required|string',
            'type' => 'required|string',
            'parent_id' => 'nullable|exists:organigrammes,id',
        ]);

        $org = Organigramme::create($request->all());
        return response()->json(['message' => 'Organigramme ajouté', 'data' => $org]);
    }

    public function modifier(Request $request, $id)
    {
        $org = Organigramme::findOrFail($id);

        $request->validate([
            'nom' => 'sometimes|required|string',
            'type' => 'sometimes|required|string',
            'parent_id' => 'nullable|exists:organigrammes,id',
        ]);

        $org->update($request->all());
        return response()->json(['message' => 'Organigramme modifié', 'data' => $org]);
    }

    public function supprimer($id)
    {
        $org = Organigramme::findOrFail($id);
        $org->delete();
        return response()->json(['message' => 'Organigramme supprimé']);
    }

    public function rechercher($id)
    {
        $org = Organigramme::with('parent')->findOrFail($id);
        return response()->json($org);
    }

*/
}

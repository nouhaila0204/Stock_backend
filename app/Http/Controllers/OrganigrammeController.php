<?php

namespace App\Http\Controllers;

use App\Models\Organigramme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OrganigrammeController extends Controller
{
    /**

     * Récupère tout l'organigramme sous forme de tableau plat pour la construction d'arbre
     * avec gestion des cas spécifiques
     */
    public function getAllForTree()
    {
        try {
            // Récupérer TOUS les éléments de l'organigramme
            $allElements = Organigramme::orderBy('parent_id')
                ->orderByRaw("
                    CASE 
                        WHEN type = 'DirectionG' THEN 1
                        WHEN type = 'Direction' THEN 2
                        WHEN type = 'Division' THEN 3
                        WHEN type = 'Département' THEN 4
                        WHEN type = 'Unité' THEN 5
                        WHEN type = 'Agence' THEN 6
                        WHEN type = 'Mission' THEN 7
                        ELSE 8 
                    END
                ")
                ->orderBy('nom')
                ->get()
                ->keyBy('id');

            // Trouver la Direction Générale (racine)
            $dg = $allElements->firstWhere('id', 1); // ID 1 est la DG

            if (!$dg) {
                throw new \Exception('Direction Générale non trouvée (ID:1)');
            }

            // Construire l'arbre à partir de la racine avec gestion des cas spécifiques
            $tree = $this->buildTreeWithSpecialCases($allElements, $dg->id);

            return response()->json([
                'success' => true,
                'data' => [$tree] // Retourner comme tableau avec un seul élément racine
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur récupération arbre organigramme: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construit l'arbre avec gestion des cas spécifiques
     */
    private function buildTreeWithSpecialCases($allElements, $parentId)
    {
        $node = $allElements->get($parentId);
        
        if (!$node) {
            return null;
        }

        // Trouver tous les enfants directs
        $children = $allElements->where('parent_id', $parentId);

        // Cas spécial : Si c'est la DG (ID:1), on inclut TOUS les éléments directs
        // même s'ils sont de types différents (Directions, Divisions, Départements, Unités, Missions)
        if ($parentId == 1) {
            $children = $children->sortBy(function($item) {
                $typeOrder = [
                    'Direction' => 1,
                    'Division' => 2,
                    'Département' => 3,
                    'Unité' => 4,
                    'Mission' => 5,
                    'Agence' => 6
                ];
                return ($typeOrder[$item->type] ?? 99) . '_' . $item->nom;
            });
        }

        // Cas spécial : Si c'est "Agences Territoriales" (ID:7, type:Unité)
        // on construit ses agences enfants
        if ($parentId == 7) {
            $children = $children->where('type', 'Agence')
                ->sortBy('nom');
        }

        // Construire récursivement les enfants
        $childrenTree = [];
        foreach ($children as $child) {
            $childTree = $this->buildTreeWithSpecialCases($allElements, $child->id);
            if ($childTree) {
                $childrenTree[] = $childTree;
            }
        }

        return [
            'id' => $node->id,
            'nom' => $node->nom,
            'type' => $node->type,
            'parent_id' => $node->parent_id,
            'children' => $childrenTree
        ];
    }

    /**
     * Version alternative plus simple pour debug
     */
    public function getAllFlat()
    {
        try {
            $organigrammes = Organigramme::orderBy('parent_id')
                ->orderBy('type')
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $organigrammes
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur récupération organigramme flat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Liste filtrée (parent_id si fourni)
     */
    public function index(Request $request)
    {
        try {
            $parentId = $request->query('parent_id');
            $type = $request->query('type');

            $query = Organigramme::query();

            if ($parentId !== null) {
                $query->where('parent_id', $parentId);
            }

            if ($type !== null) {
                $query->where('type', $type);
            }

            $organigrammes = $query->orderBy('type')->orderBy('nom')->get();

            return response()->json([
                'success' => true,
                'data' => $organigrammes,
                'total' => $organigrammes->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération organigrammes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Récupère un organigramme par son ID
     */
    public function organigrammeShow($id)
    {
        try {
            $organigramme = Organigramme::with([
                'users:id,name,email,role,poste,organigramme_id',
                'children' => function ($query) {
                    $query->orderBy('type')->orderBy('nom');
                },
                'parent:id,nom,type'
            ])->find($id);

            if (!$organigramme) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organigramme non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $organigramme,
                'stats' => [
                    'total_users' => $organigramme->users->count(),
                    'total_children' => $organigramme->children->count(),
                    'children_by_type' => $organigramme->children->groupBy('type')->map->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération organigramme: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'organigramme',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }


public function organigrammeSearch(Request $request)
{
    $request->validate([
        'nom' => 'nullable|string',
        'type' => 'nullable|string',
    ]);

    $query = Organigramme::query();

    if ($request->filled('nom')) {
        $query->where('nom', 'like', '%' . $request->nom . '%');
    }

    if ($request->filled('type')) {
        $query->where('type', 'like', '%' . $request->type . '%');
    }

    $results = $query->get();

    return response()->json([
        'message' => 'Résultats de la recherche',
        'data' => $results
    ]);
}
    



    /**
     * Récupère tous les enfants de la DG (parent_id = 1)
     */
    public function getDGChildren()
    {
        try {
            $dgChildren = Organigramme::where('parent_id', 1)
                ->orderByRaw("CASE 
                    WHEN type = 'directionG' THEN 1
                    WHEN type = 'direction' THEN 2
                    WHEN type = 'division' THEN 3
                    WHEN type = 'departement' THEN 4
                    WHEN type = 'agence_territoriale' THEN 5
                    WHEN type = 'agence' THEN 6
                    ELSE 7 END")
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $dgChildren
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération enfants DG: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des éléments DG'
            ], 500);
        }
    }

    /**
     * Récupérer les sous-directions d’une direction
     */
    public function getSousDirections($directionId)
    {
        try {
            $sousDirections = Organigramme::where('parent_id', $directionId)
                ->where('type', 'direction')
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sousDirections
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur chargement directions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Récupérer les agences d’une agence territoriale
     */
    public function getAgences($agenceTerrId)
    {
        try {
            $agences = Organigramme::where('parent_id', $agenceTerrId)
                ->where('type', 'agence')
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $agences
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur chargement agences: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Relations imbriquées avec limite de profondeur
     */
    private function getNestedRelations()
    {
        return [
            'users:id,name,email,role,organigramme_id',
            'children' => function ($query) {
                $query->with($this->getChildrenRelations(4))
                    ->orderBy('type')
                    ->orderBy('nom');
            }
        ];
    }

    private function getChildrenRelations($depth)
    {
        if ($depth <= 0) {
            return ['users:id,name,email,role,organigramme_id'];
        }

        return [
            'users:id,name,email,role,organigramme_id',
            'children' => function ($query) use ($depth) {
                $query->with($this->getChildrenRelations($depth - 1))
                    ->orderBy('type')
                    ->orderBy('nom');
            }
        ];
    }
    // App\Http\Controllers\OrganigrammeController.php
public function getChildren($id)
{
    $children = Organigramme::where('parent_id', $id)->get();

    // Cas spécial : si c'est DG → récupérer aussi les divisions, départements, agences directement liés
    $parent = Organigramme::find($id);
    if ($parent && $parent->type === 'DG') {
        $specials = Organigramme::whereIn('type', ['division', 'departement', 'agence'])
                                ->where('parent_id', $id)
                                ->get();
        $children = $children->merge($specials);
    }

    return response()->json($children);
}

}

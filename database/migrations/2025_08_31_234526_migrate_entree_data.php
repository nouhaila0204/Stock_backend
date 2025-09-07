<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Entree;

return new class extends Migration
{
    public function up()
    {
        $entrees = Entree::all();
        foreach ($entrees as $entree) {
            if ($entree->produit_id) {
                DB::table('entree_produit')->insert([
                    'entree_id' => $entree->id,
                    'produit_id' => $entree->produit_id,
                    'quantite' => $entree->quantite,
                    'prixUnitaire' => $entree->prixUnitaire,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('entree_produit')->truncate();
    }
};

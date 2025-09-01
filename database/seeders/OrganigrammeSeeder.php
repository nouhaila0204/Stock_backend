<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organigramme;

class OrganigrammeSeeder extends Seeder
{
    public function run()
    {
        $dg = Organigramme::create([
            'nom' => 'Direction Générale',
            'type' => 'direction',
            'parent_id' => null
        ]);

        $si = Organigramme::create([
            'nom' => 'Division SI',
            'type' => 'division',
            'parent_id' => $dg->id
        ]);

        Organigramme::create([
            'nom' => 'Chargé de mission Partenariat',
            'type' => 'service',
            'parent_id' => $dg->id
        ]);

        Organigramme::create([
            'nom' => 'Département Juridique',
            'type' => 'departement',
            'parent_id' => $dg->id
        ]);

        Organigramme::create([
            'nom' => 'Division Développement et AF',
            'type' => 'division',
            'parent_id' => $si->id
        ]);
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  
public function up()
    {
        Schema::table('entrees', function (Blueprint $table) {
            // Drop existing foreign key if it exists
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = 'entrees'
                AND COLUMN_NAME = 'produit_id'
                AND CONSTRAINT_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (!empty($foreignKeys)) {
                $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
            }

            // Drop columns if they exist
            if (Schema::hasColumn('entrees', 'produit_id')) {
                $table->dropColumn('produit_id');
            }
            if (Schema::hasColumn('entrees', 'quantite')) {
                $table->dropColumn('quantite');
            }
            if (Schema::hasColumn('entrees', 'prixUnitaire')) {
                $table->dropColumn('prixUnitaire');
            }

            // Add columns with proper constraints
            $table->foreignId('produit_id')->constrained('products')->onDelete('cascade')->after('fournisseur_id');
            $table->integer('quantite')->unsigned()->after('produit_id');
            $table->decimal('prixUnitaire', 10, 2)->after('quantite');
        });
    }

    public function down()
    {
        Schema::table('entrees', function (Blueprint $table) {
            // Drop the foreign key and columns
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = 'entrees'
                AND COLUMN_NAME = 'produit_id'
                AND CONSTRAINT_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (!empty($foreignKeys)) {
                $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
            }

            $table->dropColumn(['produit_id', 'quantite', 'prixUnitaire']);
        });
    }
};

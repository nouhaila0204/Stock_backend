<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Supprimer la colonne price si elle existe
            if (Schema::hasColumn('products', 'price')) {
                $table->dropColumn('price');
            }

            // Définir stock à 0 par défaut si ce n'est pas déjà le cas
            $table->integer('stock')->default(0)->change();

            // Supprimer la contrainte de clé étrangère existante sur tva_id si elle existe
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'tva_id' AND CONSTRAINT_SCHEMA = 'stock_db'");
            foreach ($foreignKeys as $foreignKey) {
                if ($foreignKey->CONSTRAINT_NAME !== 'PRIMARY') {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                }
            }

            // S'assurer que tva_id est une clé étrangère non nulle
            if (Schema::hasColumn('products', 'tva_id')) {
                $table->foreignId('tva_id')->change()->constrained()->onDelete('restrict');
            } else {
                $table->foreignId('tva_id')->constrained()->onDelete('restrict');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            // Restaurer price si supprimé
            if (!Schema::hasColumn('products', 'price')) {
                $table->decimal('price', 8, 2)->nullable();
            }

            // Supprimer la contrainte de clé étrangère sur tva_id
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'tva_id' AND CONSTRAINT_SCHEMA = 'stock_db'");
            foreach ($foreignKeys as $foreignKey) {
                if ($foreignKey->CONSTRAINT_NAME !== 'PRIMARY') {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                }
            }

            // Rendre tva_id nullable pour rollback
            $table->foreignId('tva_id')->nullable()->change();

            // Rendre stock nullable pour rollback
            $table->integer('stock')->nullable()->change();
        });
    }
};

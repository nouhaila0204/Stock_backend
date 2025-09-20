<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_xx_xx_xxxxxx_remove_product_id_and_quantite_from_demandes_table.php
public function up()
{
    Schema::table('demandes', function (Blueprint $table) {
        // Supprimer d'abord les clés étrangères si elles existent
        if (Schema::hasColumn('demandes', 'produit_id')) {
            $table->dropForeign(['produit_id']);
            $table->dropColumn('produit_id');
        }
        
    });
}

public function down()
{
    Schema::table('demandes', function (Blueprint $table) {
        // Recréer les colonnes (pour rollback)
        $table->foreignId('produit_id')->nullable()->constrained()->onDelete('cascade');
    });
}
};

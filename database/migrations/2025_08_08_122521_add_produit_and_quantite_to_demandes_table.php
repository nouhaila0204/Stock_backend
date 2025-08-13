<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('demandes', function (Blueprint $table) {
        // Ajouter une clé étrangère vers le produit (id)
        $table->foreignId('produit_id')->nullable()->constrained('products')->onDelete('cascade');

        // Ajouter le champ quantite demandée
        $table->integer('quantite')->default(0);;
    });
}

public function down()
{
    Schema::table('demandes', function (Blueprint $table) {
        $table->dropForeign(['produit_id']);
        $table->dropColumn(['produit_id', 'quantite']);
    });
}

};

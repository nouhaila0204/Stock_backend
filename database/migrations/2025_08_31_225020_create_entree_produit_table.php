<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEntreeProduitTable extends Migration
{
   public function up()
    {
        Schema::create('entree_produit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entree_id'); // Clé étrangère pour entree
            $table->unsignedBigInteger('produit_id'); // Clé étrangère pour product
            $table->integer('quantite')->unsigned();
            $table->decimal('prixUnitaire', 10, 2);
            $table->timestamps();

            // Contraintes de clés étrangères
            $table->foreign('entree_id')->references('id')->on('entrees')->onDelete('cascade');
            $table->foreign('produit_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('entree_produit');
    }
};
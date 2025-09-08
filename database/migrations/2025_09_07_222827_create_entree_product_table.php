<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
    {
        Schema::create('entree_product', function (Blueprint $table) {
            $table->foreignId('entree_id')->constrained()->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('products')->onDelete('cascade');
            $table->unsignedInteger('quantite');
            $table->decimal('prixUnitaire', 10, 2);
            $table->timestamps();
            $table->primary(['entree_id', 'produit_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('entree_product');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up()
    {
        Schema::create('sortie_product', function (Blueprint $table) {
            $table->foreignId('sortie_id')->constrained()->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantite')->unsigned();
            $table->timestamps();
            $table->primary(['sortie_id', 'produit_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sortie_product');
    }
};

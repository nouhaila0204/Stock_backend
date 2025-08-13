<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('stocks', function (Blueprint $table) {
        $table->id();

        // Clé étrangère vers produits
        $table->foreignId('produit_id')->constrained('products')->onDelete('cascade');

        // Colonnes de stock
        $table->integer('qteEntree')->default(0);
        $table->integer('qteSortie')->default(0);
        $table->integer('valeurStock')->default(0); // ou decimal si besoin
        $table->timestamps();
    });
}
  

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};

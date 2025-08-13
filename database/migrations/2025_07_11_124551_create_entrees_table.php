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
    Schema::create('entrees', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('produit_id'); // 🔗 FK vers produit
        $table->string('numBond');
        $table->string('codeMarche')->nullable();
        $table->decimal('prixUnitaire', 10, 2);
        $table->integer('quantite');
        $table->date('date');
        $table->timestamps();

        $table->foreign('produit_id')->references('id')->on('products')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrees');
    }
};

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
    Schema::create('sorties', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('produit_id');
        $table->string('destination');
        $table->text('commentaire')->nullable();
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
        Schema::dropIfExists('sorties');
    }
};

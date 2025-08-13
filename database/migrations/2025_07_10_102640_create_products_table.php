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
       Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('reference')->unique();
    $table->integer('stock');
    $table->integer('stock_min');
    $table->decimal('price', 8, 2);

    // 🔗 Clés étrangères simplifiées
    $table->foreignId('tva_id')->constrained('tvas')->onDelete('cascade');
    $table->unsignedBigInteger('tva_id')->nullable();
    $table->foreign('tva_id')->references('id')->on('tvas');
    $table->foreignId('sous_famille_produit_id')->constrained()->onDelete('cascade');

    $table->timestamps();
});
    
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
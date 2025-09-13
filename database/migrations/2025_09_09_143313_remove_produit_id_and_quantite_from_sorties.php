<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sorties', function (Blueprint $table) {
             // Drop the foreign key constraint
            $table->dropForeign(['produit_id']); // Drops the foreign key constraint
            $table->dropColumn(['produit_id', 'quantite']);
        });
    }

    public function down()
    {
        Schema::table('sorties', function (Blueprint $table) {
            $table->foreignId('produit_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantite')->unsigned();
        });
    }
};

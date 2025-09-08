<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('entrees', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['produit_id']); // Drops the foreign key constraint
            // Drop the columns
            $table->dropColumn(['produit_id', 'quantite', 'prixUnitaire']);
        });
    }

    public function down()
    {
        Schema::table('entrees', function (Blueprint $table) {
            $table->foreignId('produit_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->integer('quantite')->unsigned()->nullable();
            $table->decimal('prixUnitaire', 10, 2)->nullable();
        });
    }
};

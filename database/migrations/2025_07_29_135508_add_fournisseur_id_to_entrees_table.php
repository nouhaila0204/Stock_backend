<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFournisseurIdToEntreesTable extends Migration
{
    public function up()
    {
        Schema::table('entrees', function (Blueprint $table) {
            // Ajouter la colonne avec nullable() pour éviter problème si données non conformes
            $table->unsignedBigInteger('fournisseur_id')->nullable()->after('id');

            // Ajouter la clé étrangère
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('entrees', function (Blueprint $table) {
            $table->dropForeign(['fournisseur_id']);
            $table->dropColumn('fournisseur_id');
        });
    }
}

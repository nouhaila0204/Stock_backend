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
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('adresse')->nullable(); // Champ texte, nullable (peut être vide)
            $table->string('telephone')->nullable(); // Champ texte, nullable (peut être vide)
        });
    }

    public function down()
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn(['adresse', 'telephone']); // Revert en cas de rollback
        });
    }
};

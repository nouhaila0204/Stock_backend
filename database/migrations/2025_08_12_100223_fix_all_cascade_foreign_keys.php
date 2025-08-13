<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixAllCascadeForeignKeys extends Migration
{
    public function up()
    {
        // 1. Table stocks - CASCADE à corriger
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->foreign('produit_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('restrict'); // Un produit ne peut pas être supprimé s'il a du stock
        });

        // 2. Table sous_familles - CASCADE à corriger
        Schema::table('sous_famille_produits', function (Blueprint $table) {
            $table->dropForeign(['famille_produit_id']);
            $table->foreign('famille_produit_id')
                  ->references('id')
                  ->on('famille_produits') // Vérifiez le nom de votre table
                  ->onDelete('restrict'); // Une famille ne peut pas être supprimée si elle a des sous-familles
        });

        // 3. Table demandes - CASCADE à corriger  
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict'); // Un utilisateur ne peut pas être supprimé s'il a des demandes
        });

        // 4. Table alertes - CASCADE à corriger
        Schema::table('alertes', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->foreign('produit_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('restrict'); // Les alertes peuvent être supprimées avec le produit, mais moi je veux qu'ils restent
        });

        // 5. Table entrees - CASCADE à corriger
        Schema::table('entrees', function (Blueprint $table) {
            $table->dropForeign(['fournisseur_id']);
            $table->dropForeign(['produit_id']);
            
            $table->foreign('fournisseur_id')
                  ->references('id')
                  ->on('fournisseurs')
                  ->onDelete('restrict'); // Un fournisseur ne peut pas être supprimé s'il a des entrées
                  
            $table->foreign('produit_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('restrict'); // Un produit ne peut pas être supprimé s'il a des entrées
        });

        // 6. Table sorties - CASCADE à corriger
        Schema::table('sorties', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->foreign('produit_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('restrict'); // Un produit ne peut pas être supprimé s'il a des sorties
        });

        // 7. Table products - CASCADE à corriger
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tva_id']);
            $table->dropForeign(['sous_famille_produit_id']);
            
            $table->foreign('tva_id')
                  ->references('id')
                  ->on('tvas')
                  ->onDelete('restrict'); // Une TVA ne peut pas être supprimée si des produits l'utilisent
                  
            $table->foreign('sous_famille_produit_id')
                  ->references('id')
                  ->on('sous_famille_produits') // Vérifiez le nom de votre table
                  ->onDelete('restrict'); // Une sous-famille ne peut pas être supprimée si des produits l'utilisent
        });
    }

    public function down()
    {
        // Remettre tous les CASCADE pour le rollback
        
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->foreignId('produit_id')->constrained('products')->onDelete('cascade');
        });

        Schema::table('sous_familles', function (Blueprint $table) {
            $table->dropForeign(['famille_produit_id']);
            $table->foreignId('famille_produit_id')->constrained()->onDelete('cascade');
        });

        Schema::table('demandes', function (Blueprint $table) {
            $table->dropForeign(['employe_id']);
            $table->foreignId('employe_id')->constrained()->onDelete('cascade');
        });

        Schema::table('alertes', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->foreignId('produit_id')->constrained('products')->onDelete('cascade');
        });

        Schema::table('entrees', function (Blueprint $table) {
            $table->dropForeign(['fournisseur_id']);
            $table->dropForeign(['produit_id']);
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');
            $table->foreign('produit_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::table('sorties', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->foreign('produit_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tva_id']);
            $table->dropForeign(['sous_famille_produit_id']);
            $table->foreignId('tva_id')->constrained('tvas')->onDelete('cascade');
            $table->foreignId('sous_famille_produit_id')->constrained()->onDelete('cascade');
        });
    }
}
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
    Schema::create('demandes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employe_id')->constrained()->onDelete('cascade');
    $table->string('raison')->nullable();
    $table->enum('etat', ['en_attente', 'accepté', 'rejeté'])->default('en_attente');
    $table->timestamps();
});

}

public function down()
{
    Schema::table('demandes', function (Blueprint $table) {
        $table->dropForeign(['employe_id']);
        $table->dropColumn('employe_id');
    });
}
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMenusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')
                  ->nullable()
                  ->default(null);
            $table->string('route_or_url')->default('#');
            $table->string('icon')
                  ->nullable()
                  ->default(null);
            $table->boolean('active')
                  ->default(true);
            $table->integer('position');
            $table->json('routes')
                  ->nullable()
                  ->default('[]');
            $table->timestamps();

            $table->foreign('parent_id')
                  ->references('id')
                  ->on('menus')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('menus');
    }
}

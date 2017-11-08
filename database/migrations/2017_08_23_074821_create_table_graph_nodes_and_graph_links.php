<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableGraphNodesAndGraphLinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('graph_nodes', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('subroutine_id')->index();
            $table->string('subroutine_type')->index();
            $table->string('label', 512);
            $table->boolean('is_symbol');
            $table->boolean('is_copy');
        });
        Schema::create('graph_links', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('source_id')->index();
            $table->integer('target_id')->index();
            $table->integer('xref');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('graph_links');
        Schema::dropIfExists('graph_nodes');
    }
}

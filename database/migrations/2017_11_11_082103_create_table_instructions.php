<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableInstructions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('instructions', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('block_id')->index();
            $table->integer('addr')->index();
            $table->integer('end')->index();
            $table->string('mne');
        });

        Schema::create('operands', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('instruction_id')->index();
            $table->tinyInteger('pos')->index();
            $table->integer('size');
            $table->string('type');

            $table->string('reg')->nullable();
            $table->bigInteger('imm')->nullable();
            $table->string('index')->nullable();
            $table->tinyInteger('scale')->nullable();
            $table->string('seg')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('instructions');
        Schema::dropIfExists('operands');
    }
}

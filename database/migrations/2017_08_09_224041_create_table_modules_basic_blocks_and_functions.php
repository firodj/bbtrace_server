<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableModulesBasicBlocksAndFunctions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('basic_blocks', function(Blueprint $table)
        {
            $table->integer('id')->primary();
            $table->integer('end');
            $table->integer('load_module_id');
        });

        Schema::create('load_modules', function(Blueprint $table)
        {
            $table->integer('id')->primary();
            $table->integer('entry');
            $table->integer('end');
            $table->string('name');
            $table->string('path');
        });

        Schema::create('import_symbols', function(Blueprint $table)
        {
            $table->integer('id')->primary();
            $table->integer('load_module_id');
            $table->string('name');
            $table->integer('ordinal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('basic_blocks');
        Schema::dropIfExists('load_modules');
        Schema::dropIfExists('import_symbol');
    }
}

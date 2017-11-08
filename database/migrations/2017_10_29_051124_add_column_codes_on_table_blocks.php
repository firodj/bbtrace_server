<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnCodesOnTableBlocks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blocks', function(Blueprint $table)
        {
            $table->json('codes')->nullable();
        });
        Schema::table('subroutines', function(Blueprint $table)
        {
            $table->json('returns')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blocks', function(Blueprint $table)
        {
            $table->dropColumn('codes');
        });
        Schema::table('subroutines', function(Blueprint $table)
        {
            $table->dropColumn('returns');
        });
    }
}

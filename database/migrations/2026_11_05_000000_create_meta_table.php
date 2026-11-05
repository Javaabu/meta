<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Javaabu\Meta\Support\MetaTable;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('meta.table', 'meta');

        Schema::create($table, function (Blueprint $blueprint) use ($table) {
            MetaTable::columns($blueprint, $table, config('meta.morph_key', 'id'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('meta.table', 'meta'));
    }
};

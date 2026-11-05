<?php

namespace Javaabu\Meta\Support;

use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;

/**
 * The columns and indexes of a meta table. Use it to create a model's own meta table:
 *
 * Schema::create('post_meta', fn (Blueprint $table) => MetaTable::columns($table, 'post_meta'));
 */
class MetaTable
{
    /**
     * @param  string  $morph_key  id, uuid or ulid: the type of the records' keys
     */
    public static function columns(Blueprint $table, string $name, string $morph_key = 'id'): void
    {
        $table->id();

        $table->string('metable_type');

        match ($morph_key) {
            'id' => $table->unsignedBigInteger('metable_id'),
            'uuid' => $table->uuid('metable_id'),
            'ulid' => $table->ulid('metable_id'),
            default => throw new InvalidArgumentException("Unknown meta morph key type [{$morph_key}]. Use id, uuid or ulid."),
        };

        $table->string('key', 100);
        $table->string('locale', 10)->default('');
        $table->string('type', 50);

        $table->string('value_string', 255)->nullable();
        $table->longText('value_text')->nullable();
        $table->bigInteger('value_int')->nullable();
        $table->double('value_float')->nullable();
        $table->dateTime('value_datetime')->nullable();
        $table->json('value_json')->nullable();

        $table->timestamps();

        // Reading a record's meta, eager loading, upserts and sorting.
        $table->unique(['metable_type', 'metable_id', 'key', 'locale'], "{$name}_record_unique");

        // whereMeta and friends: id IN (select metable_id … where type, key, value).
        $table->index(['metable_type', 'key', 'value_string', 'metable_id'], "{$name}_string_index");
        $table->index(['metable_type', 'key', 'value_int', 'metable_id'], "{$name}_int_index");
        $table->index(['metable_type', 'key', 'value_float', 'metable_id'], "{$name}_float_index");
        $table->index(['metable_type', 'key', 'value_datetime', 'metable_id'], "{$name}_datetime_index");
    }
}

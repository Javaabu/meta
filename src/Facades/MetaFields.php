<?php

namespace Javaabu\Meta\Facades;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Javaabu\Meta\Definitions\MetaField;
use Javaabu\Meta\Forms\MetaForm;
use Javaabu\Meta\MetaFields as MetaFieldsRegistry;
use Javaabu\Meta\Types\MetaType;
use Javaabu\Stencil\Fields\Field;

/**
 * @method static MetaFieldsRegistry for(string $model, array<int|string, Field|string>|Closure $fields, ?string $group = null)
 * @method static MetaFieldsRegistry forget(?string $model = null, ?string $group = null)
 * @method static array<string, MetaField> fields(Model|string $model, ?string $group = null)
 * @method static MetaField|null field(Model|string $model, string $key, ?string $group = null)
 * @method static array<int, string> keys(Model|string $model, ?string $group = null)
 * @method static array<int, string> groups(string $model)
 * @method static MetaField|null queryField(string $model, string $key)
 * @method static MetaForm form(Model $record, ?string $group = null)
 * @method static MetaFieldsRegistry type(MetaType|string $type)
 * @method static MetaType getType(string $name)
 * @method static bool hasType(string $name)
 * @method static array<string, MetaType> types()
 * @method static MetaType fieldType(Field $field)
 * @method static MetaType typeForValue(mixed $value)
 *
 * @see MetaFieldsRegistry
 */
class MetaFields extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MetaFieldsRegistry::class;
    }
}

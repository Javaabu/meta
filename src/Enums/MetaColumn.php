<?php

namespace Javaabu\Meta\Enums;

/**
 * The value columns of the meta table. Each type stores its values in one of them.
 */
enum MetaColumn: string
{
    /** varchar(255), indexed */
    case String = 'value_string';

    /** longText, not indexed */
    case Text = 'value_text';

    /** bigint, indexed */
    case Integer = 'value_int';

    /** double, indexed */
    case Float = 'value_float';

    /** datetime, indexed */
    case DateTime = 'value_datetime';

    /** json, not indexed */
    case Json = 'value_json';

    public function isIndexed(): bool
    {
        return ! in_array($this, [self::Text, self::Json], true);
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(fn (self $column) => $column->value, self::cases());
    }
}

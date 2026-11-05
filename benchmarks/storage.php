<?php

/**
 * Storage benchmark for javaabu/meta: compares the ways meta can be stored
 * and queried on SQLite and MySQL.
 *
 *   A   shared, polymorphic table with typed value columns and (type, key, value, id) indexes
 *       A/exists: whereMeta as a correlated EXISTS subquery
 *       A/in:     whereMeta as id IN (subquery)
 *   C   a table per model with the same typed columns, no metable_type
 *   B   a JSON column on the model's table
 *       B/json:  no indexes
 *       B/gen:   a generated column and an index per queried key
 *
 *   php benchmarks/storage.php --driver=sqlite --posts=100000
 *   php benchmarks/storage.php --driver=mysql --posts=100000 --host=127.0.0.1 --database=meta_bench --username=root
 *
 * Prints a Markdown table of median times in milliseconds.
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

$options = getopt('', ['driver::', 'posts::', 'runs::', 'host::', 'port::', 'database::', 'username::', 'password::', 'path::', 'reuse']);

$driver = $options['driver'] ?? 'sqlite';
$posts = (int) ($options['posts'] ?? 100000);
$runs = (int) ($options['runs'] ?? 7);
$reuse = isset($options['reuse']);

$capsule = new Capsule;

if ($driver === 'mysql') {
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $options['host'] ?? '127.0.0.1',
        'port' => $options['port'] ?? '3306',
        'database' => $options['database'] ?? 'meta_bench',
        'username' => $options['username'] ?? 'root',
        'password' => $options['password'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);
} else {
    $path = $options['path'] ?? sys_get_temp_dir() . '/javaabu-meta-bench.sqlite';

    if (! $reuse && file_exists($path)) {
        unlink($path);
    }

    touch($path);

    $capsule->addConnection(['driver' => 'sqlite', 'database' => $path]);
}

/** @var Connection $db */
$db = $capsule->getConnection();
$pdo = $db->getPdo();

if ($driver === 'sqlite') {
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA cache_size = -200000');
}

$version = $driver === 'mysql'
    ? $db->selectOne('select version() as v')->v
    : $db->selectOne('select sqlite_version() as v')->v;

fwrite(STDERR, "{$driver} {$version}, {$posts} posts\n");

$keys = ['subtitle', 'color', 'sku', 'featured', 'views', 'rating', 'event_date', 'body'];
$colors = ['red', 'green', 'blue', 'yellow', 'black', 'white', 'orange', 'purple', 'pink', 'brown'];

/*
|--------------------------------------------------------------------------
| Schema and data
|--------------------------------------------------------------------------
*/

if (! $reuse) {
    $schema = $db->getSchemaBuilder();

    foreach (['bench_meta', 'bench_post_meta', 'bench_posts', 'bench_pages'] as $table) {
        $schema->dropIfExists($table);
    }

    $schema->create('bench_posts', function ($table) {
        $table->id();
        $table->string('title');
        $table->json('meta')->nullable();
        $table->timestamps();
    });

    $schema->create('bench_pages', function ($table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    $typed_columns = function ($table) {
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
    };

    $schema->create('bench_meta', function ($table) use ($typed_columns) {
        $table->id();
        $table->string('metable_type');
        $table->unsignedBigInteger('metable_id');
        $typed_columns($table);

        $table->unique(['metable_type', 'metable_id', 'key', 'locale'], 'bench_meta_record_unique');
        $table->index(['metable_type', 'key', 'value_string', 'metable_id'], 'bench_meta_string_index');
        $table->index(['metable_type', 'key', 'value_int', 'metable_id'], 'bench_meta_int_index');
        $table->index(['metable_type', 'key', 'value_float', 'metable_id'], 'bench_meta_float_index');
        $table->index(['metable_type', 'key', 'value_datetime', 'metable_id'], 'bench_meta_datetime_index');
    });

    $schema->create('bench_post_meta', function ($table) use ($typed_columns) {
        $table->id();
        $table->unsignedBigInteger('metable_id');
        $typed_columns($table);

        $table->unique(['metable_id', 'key', 'locale'], 'bench_post_meta_record_unique');
        $table->index(['key', 'value_string', 'metable_id'], 'bench_post_meta_string_index');
        $table->index(['key', 'value_int', 'metable_id'], 'bench_post_meta_int_index');
        $table->index(['key', 'value_float', 'metable_id'], 'bench_post_meta_float_index');
        $table->index(['key', 'value_datetime', 'metable_id'], 'bench_post_meta_datetime_index');
    });

    mt_srand(42);

    $now = date('Y-m-d H:i:s');
    $start = microtime(true);

    $values = function (int $id) use ($colors): array {
        return [
            'subtitle' => 'Subtitle ' . bin2hex(random_bytes(6)),
            'color' => $colors[mt_rand(0, 9)],
            'sku' => sprintf('SKU-%08d', $id),
            'featured' => mt_rand(1, 10) === 1,
            'views' => mt_rand(0, 1000000),
            'rating' => mt_rand(0, 500) / 100,
            'event_date' => date('Y-m-d H:i:s', mt_rand(strtotime('2024-01-01'), strtotime('2026-12-31'))),
            'body' => str_repeat('ދިވެހި body text ', 10),
        ];
    };

    $row = function (array $base, string $key, mixed $value): array {
        $row = $base + [
            'key' => $key,
            'locale' => '',
            'value_string' => null,
            'value_text' => null,
            'value_int' => null,
            'value_float' => null,
            'value_datetime' => null,
            'value_json' => null,
        ];

        [$type, $column, $stored] = match ($key) {
            'subtitle', 'color', 'sku' => ['string', 'value_string', $value],
            'featured' => ['boolean', 'value_int', (int) $value],
            'views' => ['integer', 'value_int', $value],
            'rating' => ['float', 'value_float', $value],
            'event_date' => ['datetime', 'value_datetime', $value],
            'body' => ['text', 'value_text', $value],
        };

        $row['type'] = $type;
        $row[$column] = $stored;

        return $row;
    };

    $chunk = 500;

    foreach ([['bench_posts', $posts], ['bench_pages', intdiv($posts, 2)]] as [$table, $count]) {
        $morph = $table === 'bench_posts' ? 'App\\Models\\Post' : 'App\\Models\\Page';

        for ($from = 1; $from <= $count; $from += $chunk) {
            $db->beginTransaction();

            $records = [];
            $shared = [];
            $own = [];

            for ($id = $from; $id < min($from + $chunk, $count + 1); $id++) {
                $meta = $values($id);

                $records[] = [
                    'id' => $id,
                    'title' => "Record {$id}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ] + ($table === 'bench_posts' ? ['meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)] : []);

                foreach ($meta as $key => $value) {
                    $shared[] = $row(['metable_type' => $morph, 'metable_id' => $id, 'created_at' => $now, 'updated_at' => $now], $key, $value);

                    if ($table === 'bench_posts') {
                        $own[] = $row(['metable_id' => $id, 'created_at' => $now, 'updated_at' => $now], $key, $value);
                    }
                }
            }

            $db->table($table)->insert($records);

            foreach (array_chunk($shared, 500) as $part) {
                $db->table('bench_meta')->insert($part);
            }

            foreach (array_chunk($own, 500) as $part) {
                $db->table('bench_post_meta')->insert($part);
            }

            $db->commit();
        }
    }

    fwrite(STDERR, sprintf("seeded in %.1fs\n", microtime(true) - $start));

    // Generated, indexed columns for the keys the queries use (option B/gen).
    $generated = [
        'sku' => ['varchar(64)', '$.sku'],
        'color' => ['varchar(32)', '$.color'],
        'featured' => ['tinyint', '$.featured'],
        'views' => ['bigint', '$.views'],
        'event_date' => ['datetime', '$.event_date'],
    ];

    foreach ($generated as $key => [$type, $path]) {
        if ($driver === 'mysql') {
            $expression = $key === 'featured'
                ? "(json_extract(meta, '{$path}') = true)"
                : "(json_unquote(json_extract(meta, '{$path}')))";

            $db->statement("alter table bench_posts add column m_{$key} {$type} generated always as {$expression} virtual");
        } else {
            $db->statement("alter table bench_posts add column m_{$key} {$type} generated always as (json_extract(meta, '{$path}')) virtual");
        }

        $db->statement("create index bench_posts_m_{$key}_index on bench_posts (m_{$key}, id)");
    }

    if ($driver === 'sqlite') {
        $db->statement('analyze');
    } else {
        foreach (['bench_posts', 'bench_meta', 'bench_post_meta'] as $table) {
            $db->statement("analyze table {$table}");
        }
    }

    fwrite(STDERR, sprintf("indexed in %.1fs\n", microtime(true) - $start));
}

/*
|--------------------------------------------------------------------------
| Queries
|--------------------------------------------------------------------------
*/

$post = 'App\\Models\\Post';
// MySQL reads backslashes in string literals as escapes.
$post_literal = $driver === 'mysql' ? str_replace('\\', '\\\\', $post) : $post;
$json = fn (string $key) => $driver === 'mysql' ? "json_unquote(json_extract(p.meta, '$.{$key}'))" : "json_extract(p.meta, '$.{$key}')";
$json_bool = fn (string $key) => $driver === 'mysql' ? "json_extract(p.meta, '$.{$key}') = true" : "json_extract(p.meta, '$.{$key}') = 1";

/**
 * A meta condition for each option: [key, column, operator, value placeholder].
 */
$condition = function (string $option, string $key, string $column, string $sql_operator) use ($post_literal): string {
    $post = $post_literal;

    return match ($option) {
        'A/exists' => "exists (select 1 from bench_meta m where m.metable_id = p.id and m.metable_type = '{$post}' and m.key = '{$key}' and m.{$column} {$sql_operator})",
        'A/in' => "p.id in (select m.metable_id from bench_meta m where m.metable_type = '{$post}' and m.key = '{$key}' and m.{$column} {$sql_operator})",
        'C/exists' => "exists (select 1 from bench_post_meta m where m.metable_id = p.id and m.key = '{$key}' and m.{$column} {$sql_operator})",
        'C/in' => "p.id in (select m.metable_id from bench_post_meta m where m.key = '{$key}' and m.{$column} {$sql_operator})",
    };
};

$order_join = function (string $option, string $key, string $alias) use ($post_literal): string {
    $post = $post_literal;

    return match ($option) {
        'A/exists', 'A/in' => "left join bench_meta {$alias} on {$alias}.metable_id = p.id and {$alias}.metable_type = '{$post}' and {$alias}.key = '{$key}' and {$alias}.locale = ''",
        'C/exists', 'C/in' => "left join bench_post_meta {$alias} on {$alias}.metable_id = p.id and {$alias}.key = '{$key}' and {$alias}.locale = ''",
    };
};

$sku = sprintf('SKU-%08d', intdiv($posts, 3));
$date_from = '2026-01-01 00:00:00';
$date_to = '2026-03-31 23:59:59';

$queries = [
    'Q1 string =, selective (sku)' => [
        'meta' => fn ($o) => ["select p.* from bench_posts p where {$condition($o, 'sku', 'value_string', '= ?')} order by p.id desc limit 20", [$sku]],
        'B/json' => fn () => ["select p.* from bench_posts p where {$json('sku')} = ? order by p.id desc limit 20", [$sku]],
        'B/gen' => fn () => ['select p.* from bench_posts p where p.m_sku = ? order by p.id desc limit 20', [$sku]],
    ],
    'Q2 boolean =, 10% (featured), latest 20' => [
        'meta' => fn ($o) => ["select p.* from bench_posts p where {$condition($o, 'featured', 'value_int', '= ?')} order by p.id desc limit 20", [1]],
        'B/json' => fn () => ["select p.* from bench_posts p where {$json_bool('featured')} order by p.id desc limit 20", []],
        'B/gen' => fn () => ['select p.* from bench_posts p where p.m_featured = ? order by p.id desc limit 20', [1]],
    ],
    'Q3 string IN, 20% (color), count' => [
        'meta' => fn ($o) => ["select count(*) as c from bench_posts p where {$condition($o, 'color', 'value_string', 'in (?, ?)')}", ['red', 'blue']],
        'B/json' => fn () => ["select count(*) as c from bench_posts p where {$json('color')} in (?, ?)", ['red', 'blue']],
        'B/gen' => fn () => ['select count(*) as c from bench_posts p where p.m_color in (?, ?)', ['red', 'blue']],
    ],
    'Q4 int range 1% + orderBy (views)' => [
        'meta' => fn ($o) => ["select p.* from bench_posts p {$order_join($o, 'views', 'o')} where {$condition($o, 'views', 'value_int', '> ?')} order by o.value_int desc limit 20", [990000]],
        'B/json' => fn () => ["select p.* from bench_posts p where cast({$json('views')} as signed) > ? order by cast({$json('views')} as signed) desc limit 20", [990000]],
        'B/gen' => fn () => ['select p.* from bench_posts p where p.m_views > ? order by p.m_views desc limit 20', [990000]],
    ],
    'Q5 orderBy datetime, no filter' => [
        'meta' => fn ($o) => ["select p.* from bench_posts p {$order_join($o, 'event_date', 'o')} order by o.value_datetime desc limit 20", []],
        'B/json' => fn () => ["select p.* from bench_posts p order by {$json('event_date')} desc limit 20", []],
        'B/gen' => fn () => ['select p.* from bench_posts p order by p.m_event_date desc limit 20', []],
    ],
    'Q6 bool AND string + orderBy int' => [
        'meta' => fn ($o) => ["select p.* from bench_posts p {$order_join($o, 'views', 'o')} where {$condition($o, 'featured', 'value_int', '= ?')} and {$condition($o, 'color', 'value_string', '= ?')} order by o.value_int desc limit 20", [1, 'red']],
        'B/json' => fn () => ["select p.* from bench_posts p where {$json_bool('featured')} and {$json('color')} = ? order by cast({$json('views')} as signed) desc limit 20", ['red']],
        'B/gen' => fn () => ['select p.* from bench_posts p where p.m_featured = ? and p.m_color = ? order by p.m_views desc limit 20', [1, 'red']],
    ],
    'Q7 datetime range ~8%, count' => [
        'meta' => fn ($o) => ["select count(*) as c from bench_posts p where {$condition($o, 'event_date', 'value_datetime', 'between ? and ?')}", [$date_from, $date_to]],
        'B/json' => fn () => ["select count(*) as c from bench_posts p where {$json('event_date')} between ? and ?", [$date_from, $date_to]],
        'B/gen' => fn () => ['select count(*) as c from bench_posts p where p.m_event_date between ? and ?', [$date_from, $date_to]],
    ],
    'Q8 eager load all meta of 20 posts' => [
        'meta' => fn ($o) => str_starts_with($o, 'A')
            ? ['select * from bench_meta where metable_type = ? and metable_id in (' . implode(',', range(1001, 1020)) . ')', [$post]]
            : ['select * from bench_post_meta where metable_id in (' . implode(',', range(1001, 1020)) . ')', []],
        'B/json' => fn () => ['select p.* from bench_posts p where p.id between 1001 and 1020', []],
        'B/gen' => fn () => ['select p.* from bench_posts p where p.id between 1001 and 1020', []],
    ],
];

$options_list = ['A/exists', 'A/in', 'C/exists', 'C/in', 'B/json', 'B/gen'];

$measure = function (string $sql, array $bindings) use ($db, $runs): float {
    $db->select($sql, $bindings); // warm up

    $times = [];

    for ($i = 0; $i < $runs; $i++) {
        $start = hrtime(true);
        $db->select($sql, $bindings);
        $times[] = (hrtime(true) - $start) / 1e6;
    }

    sort($times);

    return $times[intdiv(count($times), 2)];
};

$results = [];

foreach ($queries as $name => $variants) {
    foreach ($options_list as $option) {
        $factory = $variants[$option] ?? $variants['meta'];
        [$sql, $bindings] = str_starts_with($option, 'B') ? $factory() : $factory($option);
        $results[$name][$option] = $measure($sql, $bindings);
    }

    fwrite(STDERR, "{$name} done\n");
}

/*
|--------------------------------------------------------------------------
| Writes: update one value on 500 records, one statement each
|--------------------------------------------------------------------------
*/

$writes = [
    'A' => fn (int $id, int $views) => $db->update("update bench_meta set value_int = ? where metable_type = ? and metable_id = ? and `key` = 'views' and locale = ''", [$views, $post, $id]),
    'C' => fn (int $id, int $views) => $db->update("update bench_post_meta set value_int = ? where metable_id = ? and `key` = 'views' and locale = ''", [$views, $id]),
    'B' => fn (int $id, int $views) => $db->update("update bench_posts set meta = json_set(meta, '$.views', ?) where id = ?", [$views, $id]),
];

if ($driver === 'sqlite') {
    $writes['A'] = fn (int $id, int $views) => $db->update("update bench_meta set value_int = ? where metable_type = ? and metable_id = ? and \"key\" = 'views' and locale = ''", [$views, $post, $id]);
    $writes['C'] = fn (int $id, int $views) => $db->update("update bench_post_meta set value_int = ? where metable_id = ? and \"key\" = 'views' and locale = ''", [$views, $id]);
}

$write_results = [];

foreach ($writes as $option => $write) {
    $start = hrtime(true);

    $db->transaction(function () use ($write, $posts) {
        for ($i = 0; $i < 500; $i++) {
            $write(mt_rand(1, $posts), mt_rand(0, 1000000));
        }
    });

    $write_results[$option] = (hrtime(true) - $start) / 1e6;
}

/*
|--------------------------------------------------------------------------
| Sizes
|--------------------------------------------------------------------------
*/

$sizes = [];

if ($driver === 'mysql') {
    foreach (['bench_meta', 'bench_post_meta', 'bench_posts'] as $table) {
        $row = $db->selectOne('select (data_length + index_length) / 1024 / 1024 as mb from information_schema.tables where table_schema = database() and table_name = ?', [$table]);
        $sizes[$table] = round((float) $row->mb, 1);
    }
}

/*
|--------------------------------------------------------------------------
| Report
|--------------------------------------------------------------------------
*/

echo "### {$driver} {$version}, " . number_format($posts) . ' posts (' . number_format($posts * 8) . ' post meta rows; the shared table also holds ' . number_format(intdiv($posts, 2) * 8) . " page meta rows)\n\n";
echo "Median of {$runs} runs, in ms.\n\n";
echo '| Query | ' . implode(' | ', $options_list) . " |\n";
echo '| --- |' . str_repeat(' ---: |', count($options_list)) . "\n";

foreach ($results as $name => $row) {
    echo "| {$name} | " . implode(' | ', array_map(fn ($ms) => number_format($ms, $ms < 10 ? 2 : 0), $row)) . " |\n";
}

echo "\nWrites, 500 single-value updates in a transaction: A " . number_format($write_results['A']) . ' ms, C ' . number_format($write_results['C']) . ' ms, B ' . number_format($write_results['B']) . " ms.\n";

if ($sizes !== []) {
    echo "\nSize on disk (data + indexes): " . implode(', ', array_map(fn ($table, $mb) => "{$table} {$mb} MB", array_keys($sizes), $sizes)) . ".\n";
}

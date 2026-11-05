# Storage benchmark results

Run on 2026-11-05 on an Apple Silicon Mac (`php benchmarks/storage.php --driver=sqlite|mysql --posts=100000`). See [the docs](../docs/advanced-usage/storage-and-performance.md) for what they mean.

### sqlite 3.45.2, 100,000 posts (800,000 post meta rows; the shared table also holds 400,000 page meta rows)

Median of 7 runs, in ms.

| Query | A/exists | A/in | C/exists | C/in | B/json | B/gen |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Q1 string =, selective (sku) | 43 | 0.02 | 26 | 0.01 | 35 | 0.01 |
| Q2 boolean =, 10% (featured), latest 20 | 0.10 | 0.80 | 0.08 | 0.74 | 0.10 | 0.04 |
| Q3 string IN, 20% (color), count | 91 | 5.84 | 69 | 5.51 | 35 | 0.39 |
| Q4 int range 1% + orderBy (views) | 62 | 0.96 | 49 | 0.81 | 38 | 0.04 |
| Q5 orderBy datetime, no filter | 58 | 58 | 47 | 48 | 39 | 0.04 |
| Q6 bool AND string + orderBy int | 48 | 6.43 | 34 | 6.75 | 38 | 6.99 |
| Q7 datetime range ~8%, count | 160 | 4.89 | 136 | 5.05 | 44 | 0.54 |
| Q8 eager load all meta of 20 posts | 0.35 | 0.35 | 0.14 | 0.14 | 0.03 | 0.03 |

Writes, 500 single-value updates in a transaction: A 43 ms, C 10 ms, B 56 ms.

### mysql 9.7.2, 100,000 posts (800,000 post meta rows; the shared table also holds 400,000 page meta rows)

Median of 7 runs, in ms.

| Query | A/exists | A/in | C/exists | C/in | B/json | B/gen |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Q1 string =, selective (sku) | 0.93 | 0.50 | 0.36 | 0.31 | 0.20 | 0.18 |
| Q2 boolean =, 10% (featured), latest 20 | 24 | 24 | 21 | 22 | 0.33 | 0.17 |
| Q3 string IN, 20% (color), count | 26 | 25 | 23 | 21 | 24 | 3.04 |
| Q4 int range 1% + orderBy (views) | 6.25 | 5.87 | 5.22 | 5.05 | 63 | 0.20 |
| Q5 orderBy datetime, no filter | 371 | 382 | 310 | 310 | 0.28 | 0.19 |
| Q6 bool AND string + orderBy int | 79 | 88 | 53 | 53 | 13 | 1.78 |
| Q7 datetime range ~8%, count | 10 | 10 | 8.92 | 9.40 | 8.70 | 0.93 |
| Q8 eager load all meta of 20 posts | 0.81 | 0.98 | 0.56 | 0.53 | 0.22 | 0.18 |

Writes, 500 single-value updates in a transaction: A 66 ms, C 43 ms, B 33 ms.

Size on disk (data + indexes): bench_meta 538.8 MB, bench_post_meta 256.7 MB, bench_posts 63.2 MB.

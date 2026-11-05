---
title: Requirements
sidebar_position: 1.1
---

This package requires:

- PHP 8.4 or higher
- Laravel 12 or 13
- MySQL 8.0+ or SQLite 3.35+, both tested in CI. The package uses upserts and JSON functions through Laravel's query builder, so other databases Laravel supports should work, but they aren't tested.
- [javaabu/stencil](https://github.com/Javaabu/stencil), for defining meta fields and rendering meta forms (installed with meta)

Optional:

- [javaabu/translatable](https://github.com/Javaabu/translatable) v3, for [translatable meta](./advanced-usage/translatable.md)
- For meta forms in Inertia React: stencil's React renderer and its requirements (see [stencil's installation docs](https://github.com/Javaabu/stencil/blob/main/docs/installation-and-setup.md))

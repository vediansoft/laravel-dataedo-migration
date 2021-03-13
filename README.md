# laravel-dataedo-migration
Migrate dataedo documentation files to laravel migration files.

This will convert Dataedo `data` folder from `documentation export` into laravel migration files.

# Installation

1. Add directory `DataEdoMigration` to `app\Console\Commands`
2. You should now have structure like `app\Console\Commands\DataEdoMigration`
3. Export dataedo documentation
4. Copy contents of `data` in `dataedo documentation export` into `app\Console\Commands\DataEdoMigration\data`

# Usage

```bash
$ php artisan dataedo:migration
```

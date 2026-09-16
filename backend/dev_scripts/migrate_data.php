<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "Starting data migration from SQLite to MySQL...\n";

// Ensure SQLite connection points to the correct file
Config::set('database.connections.sqlite_old', [
    'driver' => 'sqlite',
    'database' => database_path('database.sqlite'),
    'prefix' => '',
]);

$tablesToMigrate = [
    'users',
    'facebook_pages',
    'settings',
    'workspaces',
    'posts', // We will truncate the mock posts first
];

// Truncate existing mock data in MySQL
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
foreach ($tablesToMigrate as $table) {
    if (Schema::hasTable($table)) {
        DB::table($table)->truncate();
        echo "Truncated MySQL table: $table\n";
    }
}
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

foreach ($tablesToMigrate as $table) {
    if (!Schema::connection('sqlite_old')->hasTable($table)) {
        echo "Table $table does not exist in SQLite, skipping.\n";
        continue;
    }

    $records = DB::connection('sqlite_old')->table($table)->get();
    if ($records->isEmpty()) {
        echo "No records found in SQLite table: $table\n";
        continue;
    }

    $data = $records->map(function ($item) {
        return (array) $item;
    })->toArray();

    DB::table($table)->insert($data);
    echo "Migrated " . count($data) . " records to MySQL table: $table\n";
}

echo "Data migration completed successfully!\n";

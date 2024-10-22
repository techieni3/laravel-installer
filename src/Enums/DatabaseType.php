<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Enums;

use TechieNi3\LaravelInstaller\Contracts\HasOptions;

enum DatabaseType: string implements HasOptions
{
    case SQLite = 'sqlite';
    case MySQL = 'mysql';
    case MariaDB = 'mariadb';
    case PostgreSQL = 'pgsql';
    case SQLServer = 'sqlsrv';

    public static function toArray(): array
    {
        return array_column(self::cases(), 'name', 'value');
    }
}

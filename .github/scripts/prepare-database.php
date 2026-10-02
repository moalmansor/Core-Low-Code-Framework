<?php

// Waits for the CI database service and creates an empty database with the
// collation the architecture prescribes (§9: MySQL utf8mb4_0900_ai_ci,
// SQL Server Arabic_100_CI_AI_SC). Usage: php prepare-database.php <name>...

declare(strict_types=1);

$driver = getenv('DB_CONNECTION') ?: 'mysql';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: ($driver === 'sqlsrv' ? '1433' : '3306');
$user = $driver === 'sqlsrv' ? 'sa' : 'root';
$password = getenv('DB_ADMIN_PASSWORD') ?: '';
$dsn = $driver === 'sqlsrv'
    ? "sqlsrv:Server={$host},{$port};Encrypt=yes;TrustServerCertificate=yes"
    : "mysql:host={$host};port={$port}";

$pdo = null;
for ($i = 0; $i < 90; $i++) {
    try {
        $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $e) {
        fwrite(STDERR, "waiting for {$driver}: {$e->getMessage()}\n");
        sleep(2);
    }
}
if ($pdo === null) {
    fwrite(STDERR, "database service never became ready\n");
    exit(1);
}

foreach (array_slice($argv, 1) as $name) {
    if (! preg_match('/^[a-z_]+$/', $name)) {
        exit("bad database name\n");
    }
    if ($driver === 'sqlsrv') {
        $pdo->exec("IF DB_ID('{$name}') IS NOT NULL DROP DATABASE [{$name}]");
        $pdo->exec("CREATE DATABASE [{$name}] COLLATE Arabic_100_CI_AI_SC");
        $pdo->exec("ALTER DATABASE [{$name}] SET READ_COMMITTED_SNAPSHOT ON");
    } else {
        $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    }
    echo "created {$name} on {$driver}\n";
}

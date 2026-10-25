<?php

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '0433';

try {
    echo "Connecting to MySQL server on {$host}:{$port}...\n";
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected successfully.\n";

    echo "Resetting sams_db database...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `sams_db`; CREATE DATABASE `sams_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `sams_db`;");

    echo "Executing schema.sql...\n";
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);
    echo "Schema created successfully.\n";

    echo "Executing seed.sql...\n";
    $seedSql = file_get_contents(__DIR__ . '/seed.sql');
    $pdo->exec($seedSql);
    echo "Seed data inserted successfully.\n";

    echo "\n=== SAMS Database Setup Completed Successfully for Rajarata University of Sri Lanka! ===\n";
} catch (PDOException $e) {
    echo "ERROR during database setup: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
namespace App\Core;

use PDO;

abstract class Model
{
    protected static ?PDO $db = null;

    public static function getDb(): PDO
    {
        if (self::$db === null) {
            self::$db = getDB();
        }
        return self::$db;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getDb()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function fetchColumn(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetchColumn();
    }

    public static function lastInsertId(): int
    {
        return (int)self::getDb()->lastInsertId();
    }
}

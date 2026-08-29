<?php
namespace App\Models;

use App\Core\Model;

class Category extends Model
{
    public static function all(): array
    {
        return self::fetchAll("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC");
    }

    public static function findById(int $id): ?array
    {
        return self::fetchOne("SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL", [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::fetchOne("SELECT * FROM categories WHERE slug = ? AND deleted_at IS NULL", [$slug]);
    }

    public static function create(string $name, string $slug, ?string $description = null): int
    {
        self::query("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)", [$name, $slug, $description]);
        return self::lastInsertId();
    }

    public static function update(int $id, string $name, string $slug, ?string $description = null): void
    {
        self::query("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?", [$name, $slug, $description, $id]);
    }

    public static function softDelete(int $id): void
    {
        self::query("UPDATE categories SET deleted_at = NOW() WHERE id = ?", [$id]);
    }
}

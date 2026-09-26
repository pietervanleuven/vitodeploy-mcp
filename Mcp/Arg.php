<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp;

/**
 * JSON Schema snippets for tool arguments. Anything built with optional()
 * is left out of the tool's `required` list.
 */
final class Arg
{
    /**
     * @return array<string, mixed>
     */
    public static function integer(string $description, ?int $minimum = null): array
    {
        return array_filter(['type' => 'integer', 'description' => $description, 'minimum' => $minimum], fn ($v) => $v !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function string(string $description): array
    {
        return ['type' => 'string', 'description' => $description];
    }

    /**
     * @return array<string, mixed>
     */
    public static function boolean(string $description): array
    {
        return ['type' => 'boolean', 'description' => $description];
    }

    /**
     * @param  list<string>  $values
     * @return array<string, mixed>
     */
    public static function enum(array $values, string $description): array
    {
        return ['type' => 'string', 'enum' => $values, 'description' => $description];
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    public static function array(array $items, string $description): array
    {
        return ['type' => 'array', 'items' => $items, 'description' => $description];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function optional(array $schema): array
    {
        return $schema + ['x-optional' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public static function projectId(): array
    {
        return self::integer('Project ID (see vito_list_projects)');
    }

    /**
     * @return array<string, mixed>
     */
    public static function serverId(): array
    {
        return self::integer('Server ID (see vito_list_servers)');
    }

    /**
     * @return array<string, mixed>
     */
    public static function siteId(): array
    {
        return self::integer('Site ID (see vito_list_sites)');
    }

    /**
     * @return array<string, mixed>
     */
    public static function page(): array
    {
        return self::optional(self::integer('Page number (results are paginated, 25 per page)', 1));
    }
}

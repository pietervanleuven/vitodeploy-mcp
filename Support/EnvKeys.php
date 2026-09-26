<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Support;

/**
 * Reduces Vito's .env response to variable names, so values (which may be
 * secrets) never reach the model.
 */
final class EnvKeys
{
    /**
     * @param  array<string, mixed>  $data  the `data` of Vito's env response
     * @return list<string>
     */
    public static function from(array $data): array
    {
        if (is_array($data['variables'] ?? null)) {
            return array_values(array_filter(array_map(
                fn ($variable) => is_array($variable) && is_string($variable['key'] ?? null) ? $variable['key'] : null,
                $data['variables'],
            )));
        }

        $keys = [];
        foreach (preg_split('/\R/', is_string($data['env'] ?? null) ? $data['env'] : '') ?: [] as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=/', $line, $match)) {
                $keys[] = $match[1];
            }
        }

        return $keys;
    }
}

<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /** @var list<Closure(): iterable<Tool>> */
    private static array $providers = [];

    /**
     * @param  list<Tool>  $tools
     */
    public function __construct(array $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name] = $tool;
        }
    }

    /**
     * Lets another plugin add tools. The provider is evaluated lazily on every
     * default() call, so plugin boot order does not matter. Extension tools
     * are ordinary Tool objects that run through named Vito API routes; a name
     * that clashes with a core tool (or an earlier extension) is ignored.
     *
     * @param  Closure(): iterable<Tool>  $provider
     */
    public static function extend(Closure $provider): void
    {
        self::$providers[] = $provider;
    }

    /** Removes every extension provider (for tests). */
    public static function flushExtensions(): void
    {
        self::$providers = [];
    }

    public static function default(): self
    {
        $core = [
            ...Tools\Projects::tools(),
            ...Tools\Servers::tools(),
            ...Tools\Sites::tools(),
            ...Tools\Databases::tools(),
            ...Tools\Services::tools(),
            ...Tools\Workers::tools(),
            ...Tools\CronJobs::tools(),
            ...Tools\Firewall::tools(),
            ...Tools\Workflows::tools(),
            ...Tools\SshKeys::tools(),
        ];

        $names = array_fill_keys(array_map(fn (Tool $tool) => $tool->name, $core), true);
        $tools = $core;

        foreach (self::$providers as $provider) {
            try {
                $extra = [...$provider()];
            } catch (Throwable $e) {
                Log::warning('MCP tool provider skipped: '.$e->getMessage());

                continue;
            }

            /** @var list<mixed> $extra */
            foreach ($extra as $tool) {
                if (! $tool instanceof Tool) {
                    Log::warning('MCP tool provider returned a non-Tool value; ignored.');

                    continue;
                }
                if (isset($names[$tool->name])) {
                    Log::warning("MCP extension tool '{$tool->name}' ignored: the name is already registered.");

                    continue;
                }
                $names[$tool->name] = true;
                $tools[] = $tool;
            }
        }

        return new self($tools);
    }

    /**
     * Only the read-only tools, for tokens without the `write` ability.
     */
    public function readOnly(): self
    {
        return new self(array_values(array_filter($this->tools, fn (Tool $tool) => $tool->isReadOnly())));
    }

    public function find(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }
}

<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  list<Tool>  $tools
     */
    public function __construct(array $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name] = $tool;
        }
    }

    public static function default(): self
    {
        return new self([
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
        ]);
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

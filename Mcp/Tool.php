<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp;

use Closure;

/**
 * One MCP tool: its listing metadata plus the handler that runs it.
 *
 * Every tool is either read-only or mutating. Mutating tools are hidden from
 * tokens without the `write` ability, mirroring Vito's own API.
 */
final class Tool
{
    private string $title = '';

    private string $description = '';

    /** @var array<string, array<string, mixed>> */
    private array $properties = [];

    /** @var list<string> */
    private array $required = [];

    /** @var array<string, bool> */
    private array $annotations = [];

    private ?Closure $handler = null;

    /** @var list<string> */
    private array $routes = [];

    private function __construct(public readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties  JSON Schema per argument
     * @param  list<string>|null  $required  defaults to every argument not marked optional
     */
    public function input(array $properties, ?array $required = null): self
    {
        $this->required = $required ?? array_keys(array_filter($properties, fn (array $p) => ! ($p['x-optional'] ?? false)));
        $this->properties = array_map(function (array $p): array {
            unset($p['x-optional']);

            return $p;
        }, $properties);

        return $this;
    }

    /** No writes. */
    public function readOnly(): self
    {
        $this->annotations = ['readOnlyHint' => true, 'openWorldHint' => false];

        return $this;
    }

    /** Adds or triggers something without touching existing state. */
    public function nonDestructive(): self
    {
        $this->annotations = ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false];

        return $this;
    }

    /** Deletes or replaces existing state; retry-safe with the same arguments. */
    public function destructive(): self
    {
        $this->annotations = ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false];

        return $this;
    }

    /** Destructive, and every call has a fresh effect (reboot, restart, ...). */
    public function disruptive(): self
    {
        $this->annotations = ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false];

        return $this;
    }

    /**
     * Run the tool as a call to one of Vito's named API routes, passing the
     * arguments through unchanged (see ApiDispatcher::call()).
     */
    public function route(string $routeName): self
    {
        $this->routes = [$routeName];

        return $this->handle(fn (array $arguments, ToolContext $context) => $context->api->call($routeName, $arguments));
    }

    /**
     * @param  Closure(array<string, mixed>, ToolContext): mixed  $handler
     * @param  list<string>  $routes  the Vito API routes the handler calls
     */
    public function handle(Closure $handler, array $routes = []): self
    {
        $this->handler = $handler;
        $this->routes = $routes === [] ? $this->routes : $routes;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    public function isReadOnly(): bool
    {
        return ($this->annotations['readOnlyHint'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) $this->properties,
            'required' => $this->required,
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListing(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema(),
            'annotations' => ['title' => $this->title] + $this->annotations,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(array $arguments, ToolContext $context): mixed
    {
        if ($this->handler === null) {
            throw new ToolError("Tool {$this->name} has no handler.");
        }

        return ($this->handler)($arguments, $context);
    }
}

<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp;

use Closure;
use Throwable;

/**
 * A stateless MCP server: handles one JSON-RPC message at a time and supports
 * the tools capability only.
 */
final class Server
{
    /** Newest first; the first entry is offered when the client's is unknown. */
    public const PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

    private const PARSE_ERROR = -32700;

    private const INVALID_REQUEST = -32600;

    private const METHOD_NOT_FOUND = -32601;

    private const INVALID_PARAMS = -32602;

    private const INSTRUCTIONS = 'Manage this VitoDeploy instance: servers, sites, deployments, databases, workers, cron jobs, '
        .'firewall rules and more. Start with vito_list_projects, then vito_list_servers and vito_list_sites to find IDs. '
        .'Tools act with the permissions of the API key in use. Confirm destructive actions with the user first.';

    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly string $version,
        private readonly ?Closure $report = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function parseError(): array
    {
        return self::error(null, self::PARSE_ERROR, 'Parse error');
    }

    /**
     * @return array<string, mixed>|null the response, or null for notifications and client responses
     */
    public function handle(mixed $message, ToolContext $context): ?array
    {
        if (! is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return self::error(null, self::INVALID_REQUEST, 'Invalid Request');
        }

        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;

        // Notifications (no id) and responses to server requests need no answer.
        if (! array_key_exists('id', $message)) {
            if (! is_string($method)) {
                return self::error(null, self::INVALID_REQUEST, 'Invalid Request');
            }

            return null;
        }

        if ($method === null && (array_key_exists('result', $message) || array_key_exists('error', $message))) {
            return null;
        }

        if (! is_string($method) || ! (is_string($id) || is_int($id))) {
            return self::error(is_string($id) || is_int($id) ? $id : null, self::INVALID_REQUEST, 'Invalid Request');
        }

        $params = $message['params'] ?? [];
        if (! is_array($params)) {
            return self::error($id, self::INVALID_PARAMS, 'params must be an object');
        }

        return match ($method) {
            'initialize' => self::result($id, $this->initialize($params)),
            'ping' => self::result($id, (object) []),
            'tools/list' => self::result($id, ['tools' => array_map(fn (Tool $tool) => $tool->toListing(), $this->tools->all())]),
            'tools/call' => $this->callTool($id, $params, $context),
            default => self::error($id, self::METHOD_NOT_FOUND, "Method not found: {$method}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'vitodeploy', 'title' => 'VitoDeploy', 'version' => $this->version],
            'instructions' => self::INSTRUCTIONS,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(string|int $id, array $params, ToolContext $context): array
    {
        $name = $params['name'] ?? null;
        $tool = is_string($name) ? $this->tools->find($name) : null;
        if ($tool === null) {
            return self::error($id, self::INVALID_PARAMS, 'Unknown tool: '.(is_string($name) ? $name : '(none)'));
        }

        $arguments = $params['arguments'] ?? [];
        if (! is_array($arguments) || ($arguments !== [] && array_is_list($arguments))) {
            return self::error($id, self::INVALID_PARAMS, 'arguments must be an object');
        }

        try {
            return self::result($id, self::content($tool->call($arguments, $context), false));
        } catch (ToolError $e) {
            return self::result($id, self::content($e->getMessage(), true));
        } catch (Throwable $e) {
            if ($this->report !== null) {
                ($this->report)($e);
            }

            return self::result($id, self::content("Internal error while running {$tool->name}.", true));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function content(mixed $data, bool $isError): array
    {
        $text = is_string($data)
            ? $data
            : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError];
    }

    /**
     * @return array<string, mixed>
     */
    private static function result(string|int $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    private static function error(string|int|null $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}

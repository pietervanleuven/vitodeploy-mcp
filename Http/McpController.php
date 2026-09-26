<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Http;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Server;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolContext;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolRegistry;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Plugin;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Support\ApiDispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;

/**
 * MCP Streamable HTTP endpoint, stateless: every POST carries one JSON-RPC
 * message and gets a JSON answer. No SSE stream and no sessions.
 */
final class McpController
{
    public function __invoke(Request $request, Router $router, ExceptionHandler $exceptions): Response|JsonResponse
    {
        if (! $request->isMethod('POST')) {
            return response('', 405, ['Allow' => 'POST']);
        }

        // Guards against DNS rebinding from browsers, as the MCP spec requires.
        if (! $this->originAllowed($request)) {
            return response()->json(['error' => 'Origin not allowed'], 403);
        }

        $version = $request->header('MCP-Protocol-Version');
        if ($version !== null && ! in_array($version, Server::PROTOCOL_VERSIONS, true)) {
            return response()->json(['error' => "Unsupported MCP-Protocol-Version: {$version}"], 400);
        }

        $message = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(Server::parseError(), 400);
        }

        $tools = ToolRegistry::default();
        if (! $request->user()?->tokenCan('write')) {
            $tools = $tools->readOnly();
        }

        $server = new Server($tools, Plugin::version(), fn ($e) => $exceptions->report($e));
        $response = $server->handle($message, new ToolContext(new ApiDispatcher($request, $router, $exceptions)));

        return $response === null ? response('', 202) : response()->json($response);
    }

    private function originAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null) {
            return true;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        $allowed = array_filter([$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);

        return is_string($host) && in_array(strtolower($host), array_map('strtolower', $allowed), true);
    }
}

<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp;

use App\Plugins\AbstractPlugin;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Http\McpController;
use Illuminate\Support\Facades\Route;

class Plugin extends AbstractPlugin
{
    public const ROUTE_NAME = 'vitodeploy-mcp';

    protected string $name = 'MCP Server';

    protected string $description = 'Model Context Protocol endpoint at /api/mcp: manage Vito from Claude and other AI agents with your API keys';

    private static ?string $version = null;

    public function boot(): void
    {
        // Same middleware as Vito's own API routes; authorization happens per
        // tool, in the API routes each tool calls.
        // The name goes on the registrar, before the route exists, so the
        // router's name lookup picks it up without a refresh.
        Route::middleware(['api', 'auth:sanctum'])
            ->name(self::ROUTE_NAME)
            ->match(['GET', 'POST', 'DELETE'], 'api/mcp', McpController::class);
    }

    public static function version(): string
    {
        return self::$version ??= (string) (json_decode((string) file_get_contents(__DIR__.'/composer.json'), true)['version'] ?? 'dev');
    }
}

<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\tests\Support;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Plugin;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Shared helpers for the plugin test-suite.
 *
 * The suite runs inside a VitoDeploy checkout (see README, "Development"),
 * so Vito's own Tests\TestCase provides $this->user, $this->server and
 * $this->site. Vito only boots plugins that are enabled in the database, which
 * is never the case under RefreshDatabase, so every test boots it by hand.
 */
trait InteractsWithMcp
{
    public function bootPlugin(): void
    {
        (new Plugin)->boot();
    }

    /**
     * Sends one JSON-RPC request to the MCP endpoint as $this->user.
     *
     * @param  array<string, mixed>  $params
     * @param  list<string>  $abilities
     */
    public function mcp(string $method, array $params = [], array $abilities = ['read', 'write']): TestResponse
    {
        Sanctum::actingAs($this->user, $abilities);

        return $this->postJson(route(Plugin::ROUTE_NAME), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => (object) $params,
        ]);
    }

    /**
     * Calls a tool and returns its result: whether it failed, the text, and
     * the text decoded as JSON when it is JSON.
     *
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $abilities
     * @return array{isError: bool, text: string, data: mixed}
     */
    public function callTool(string $name, array $arguments = [], array $abilities = ['read', 'write']): array
    {
        $result = $this->mcp('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $abilities)
            ->assertOk()
            ->json('result');

        $text = $result['content'][0]['text'];

        return ['isError' => $result['isError'], 'text' => $text, 'data' => json_decode($text, true)];
    }

    /**
     * @return array{project_id: int, server_id: int}
     */
    public function serverIds(): array
    {
        return ['project_id' => $this->server->project_id, 'server_id' => $this->server->id];
    }
}

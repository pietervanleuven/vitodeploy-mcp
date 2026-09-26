<?php

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Server;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Plugin;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tests\Support\InteractsWithMcp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithMcp::class);

beforeEach(function (): void {
    $this->bootPlugin();
});

test('registers the endpoint at /api/mcp', function (): void {
    expect(route(Plugin::ROUTE_NAME, absolute: false))->toBe('/api/mcp');
});

test('initialize echoes a supported protocol version', function (string $version): void {
    $this->mcp('initialize', ['protocolVersion' => $version, 'capabilities' => [], 'clientInfo' => ['name' => 't', 'version' => '1']])
        ->assertOk()
        ->assertJsonPath('result.protocolVersion', $version)
        ->assertJsonPath('result.capabilities.tools.listChanged', false)
        ->assertJsonPath('result.serverInfo.name', 'vitodeploy')
        ->assertJsonPath('result.serverInfo.version', Plugin::version());
})->with(Server::PROTOCOL_VERSIONS);

test('initialize offers the newest version for an unknown one', function (): void {
    $this->mcp('initialize', ['protocolVersion' => '2020-01-01'])
        ->assertJsonPath('result.protocolVersion', Server::PROTOCOL_VERSIONS[0]);
});

test('answers ping', function (): void {
    $this->mcp('ping')->assertOk()->assertExactJson(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]);
});

test('accepts notifications with 202 and no body', function (): void {
    Sanctum::actingAs($this->user, ['read']);

    $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])
        ->assertStatus(202)
        ->assertContent('');
});

test('rejects unknown methods', function (): void {
    $this->mcp('resources/list')->assertOk()->assertJsonPath('error.code', -32601);
});

test('rejects invalid JSON with a parse error', function (): void {
    Sanctum::actingAs($this->user, ['read']);

    $this->call('POST', '/api/mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{not json')
        ->assertStatus(400)
        ->assertJsonPath('error.code', -32700);
});

test('rejects batches and non-JSON-RPC payloads', function (array $payload): void {
    Sanctum::actingAs($this->user, ['read']);

    $this->postJson('/api/mcp', $payload)->assertOk()->assertJsonPath('error.code', -32600);
})->with([
    'batch' => [[['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']]],
    'wrong version' => [['jsonrpc' => '1.0', 'id' => 1, 'method' => 'ping']],
    'object id' => [['jsonrpc' => '2.0', 'id' => ['x' => 1], 'method' => 'ping']],
]);

test('only accepts POST', function (string $method): void {
    Sanctum::actingAs($this->user, ['read']);

    $this->json($method, '/api/mcp')->assertStatus(405)->assertHeader('Allow', 'POST');
})->with(['GET', 'DELETE']);

test('rejects an unsupported MCP-Protocol-Version header', function (): void {
    Sanctum::actingAs($this->user, ['read']);

    $this->withHeader('MCP-Protocol-Version', '1999-01-01')
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertStatus(400);
});

test('rejects foreign browser origins', function (): void {
    Sanctum::actingAs($this->user, ['read']);

    $this->withHeader('Origin', 'https://evil.example')
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertForbidden();
});

test('allows its own origin', function (): void {
    Sanctum::actingAs($this->user, ['read']);

    $this->withHeader('Origin', config('app.url'))
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertOk();
});

test('requires authentication', function (): void {
    $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertUnauthorized();
});

test('works end to end with a real API token', function (): void {
    $token = $this->user->createToken('mcp', ['read'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'vito_get_server', 'arguments' => $this->serverIds()],
        ])
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});

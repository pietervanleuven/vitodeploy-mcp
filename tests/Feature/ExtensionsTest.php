<?php

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolRegistry;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\tests\Support\InteractsWithMcp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithMcp::class);

beforeEach(function (): void {
    $this->bootPlugin();
    ToolRegistry::flushExtensions();
});

afterEach(function (): void {
    ToolRegistry::flushExtensions();
});

function extensionTool(string $name, bool $readOnly = true): Tool
{
    $tool = Tool::make($name)->title($name)->description('Extension tool.')->input([]);
    $tool = $readOnly ? $tool->readOnly() : $tool->nonDestructive();

    return $tool->route('api.projects.index');
}

test('extension tools are added to the default registry', function (): void {
    $core = count(ToolRegistry::default()->all());

    ToolRegistry::extend(fn () => [extensionTool('ext_one'), extensionTool('ext_two')]);

    expect(ToolRegistry::default()->all())->toHaveCount($core + 2)
        ->and(ToolRegistry::default()->find('ext_one'))->not->toBeNull();
});

test('providers are evaluated lazily', function (): void {
    $calls = 0;
    ToolRegistry::extend(function () use (&$calls): array {
        $calls++;

        return [extensionTool('ext_lazy')];
    });

    expect($calls)->toBe(0);
    ToolRegistry::default();
    expect($calls)->toBe(1);
});

test('core tools win name collisions and a warning is logged', function (): void {
    $core = ToolRegistry::default()->find('vito_list_projects');
    Log::spy();

    ToolRegistry::extend(fn () => [extensionTool('vito_list_projects', false)]);

    $found = ToolRegistry::default()->find('vito_list_projects');
    expect($found->isReadOnly())->toBe($core->isReadOnly())
        ->and($found->toListing())->toEqual($core->toListing());
    Log::shouldHaveReceived('warning')->once();
});

test('a throwing provider is skipped and logged', function (): void {
    Log::spy();
    ToolRegistry::extend(fn () => throw new RuntimeException('boom'));
    ToolRegistry::extend(fn () => [extensionTool('ext_after')]);

    expect(ToolRegistry::default()->find('ext_after'))->not->toBeNull();
    Log::shouldHaveReceived('warning')->once();
});

test('read-only filtering applies to extension tools', function (): void {
    ToolRegistry::extend(fn () => [extensionTool('ext_read'), extensionTool('ext_write', false)]);

    $readOnly = ToolRegistry::default()->readOnly();
    expect($readOnly->find('ext_read'))->not->toBeNull()
        ->and($readOnly->find('ext_write'))->toBeNull();
});

test('flushExtensions removes extension tools', function (): void {
    ToolRegistry::extend(fn () => [extensionTool('ext_gone')]);
    ToolRegistry::flushExtensions();

    expect(ToolRegistry::default()->find('ext_gone'))->toBeNull();
});

test('extension tools are listed and callable through the endpoint', function (): void {
    ToolRegistry::extend(fn () => [extensionTool('ext_call')]);

    $names = collect($this->mcp('tools/list')->assertOk()->json('result.tools'))->pluck('name');
    expect($names)->toContain('ext_call');

    $readNames = collect($this->mcp('tools/list', [], ['read'])->json('result.tools'))->pluck('name');
    expect($readNames)->toContain('ext_call');

    $result = $this->callTool('ext_call', [], ['read']);
    expect($result['isError'])->toBeFalse();
});

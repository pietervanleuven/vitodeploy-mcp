<?php

use App\Enums\UserRole;
use App\Enums\WorkerStatus;
use App\Facades\SSH;
use App\Models\CronJob;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Project;
use App\Models\Server;
use App\Models\Site;
use App\Models\SourceControl;
use App\Models\Worker;
use App\SourceControlProviders\Github;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolRegistry;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Support\EnvKeys;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\tests\Support\InteractsWithMcp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithMcp::class);

beforeEach(function (): void {
    $this->bootPlugin();
});

describe('registry', function (): void {
    test('every tool calls Vito API routes that exist in this Vito version', function (): void {
        foreach (ToolRegistry::default()->all() as $tool) {
            expect($tool->routes())->not->toBeEmpty("{$tool->name} declares no routes");
            foreach ($tool->routes() as $route) {
                expect(Route::has($route))->toBeTrue("{$tool->name}: route {$route} does not exist");
            }
        }
    });

    test('every required route parameter has a matching argument', function (): void {
        foreach (ToolRegistry::default()->all() as $tool) {
            $properties = array_keys((array) $tool->toListing()['inputSchema']['properties']);
            foreach ($tool->routes() as $name) {
                $route = Route::getRoutes()->getByName($name);
                foreach ($route->parameterNames() as $parameter) {
                    if (str_contains($route->uri(), "{{$parameter}?}")) {
                        continue;
                    }
                    $argument = str($parameter)->snake().'_id';
                    expect(in_array($argument, $properties, true))->toBeTrue("{$tool->name}: {$argument} for {$name}");
                }
            }
        }
    });

    test('every tool is named, described and annotated', function (): void {
        $tools = ToolRegistry::default()->all();

        expect($tools)->toHaveCount(51);
        foreach ($tools as $tool) {
            $listing = $tool->toListing();
            expect($listing['name'])->toMatch('/^vito_[a-z_]+$/')
                ->and($listing['title'])->not->toBeEmpty()
                ->and($listing['description'])->not->toBeEmpty()
                ->and($listing['annotations']['openWorldHint'])->toBeFalse()
                ->and(array_diff($listing['inputSchema']['required'], array_keys((array) $listing['inputSchema']['properties'])))->toBeEmpty();
        }
    });

    test('deletes are destructive', function (): void {
        $deletes = array_filter(ToolRegistry::default()->all(), fn (Tool $tool) => str_starts_with($tool->name, 'vito_delete_'));

        expect($deletes)->not->toBeEmpty();
        foreach ($deletes as $tool) {
            expect($tool->toListing()['annotations']['destructiveHint'])->toBeTrue();
        }
    });
});

describe('tokens', function (): void {
    test('a write token sees every tool', function (): void {
        expect($this->mcp('tools/list')->json('result.tools'))->toHaveCount(51);
    });

    test('a read-only token sees only read-only tools', function (): void {
        $tools = collect($this->mcp('tools/list', abilities: ['read'])->json('result.tools'));

        expect($tools)->not->toBeEmpty()
            ->and($tools->pluck('annotations.readOnlyHint')->unique()->all())->toBe([true])
            ->and($tools->pluck('name'))->not->toContain('vito_delete_site');
    });

    test('a read-only token cannot call a write tool', function (): void {
        $this->mcp('tools/call', ['name' => 'vito_reboot_server', 'arguments' => $this->serverIds()], ['read'])
            ->assertJsonPath('error.code', -32602);
    });

    test('a project-scoped token cannot reach other projects', function (): void {
        $other = Project::factory()->create();
        $other->users()->create(['user_id' => $this->user->id, 'role' => UserRole::ADMIN]);
        $server = Server::factory()->create(['project_id' => $other->id]);
        $token = $this->user->createToken('scoped', ['read', 'project:'.$this->server->project_id])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => 'vito_get_server', 'arguments' => ['project_id' => $other->id, 'server_id' => $server->id]],
            ])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', fn (string $text) => str_contains($text, 'failed with 403'));
    });
});

describe('calls', function (): void {
    test('returns the API response', function (): void {
        $result = $this->callTool('vito_get_server', $this->serverIds());

        expect($result['isError'])->toBeFalse()
            ->and($result['data']['id'])->toBe($this->server->id)
            ->and($result['data']['name'])->toBe($this->server->name);
    });

    test('passes other arguments as the query string on GET', function (): void {
        $result = $this->callTool('vito_list_servers', ['project_id' => $this->server->project_id, 'page' => 2]);

        expect($result['isError'])->toBeFalse()
            ->and($result['data']['data'])->toBe([])
            ->and($result['data']['meta']['current_page'])->toBe(2);
    });

    test('reports API errors as tool errors', function (): void {
        $result = $this->callTool('vito_get_server', ['project_id' => $this->server->project_id, 'server_id' => 999999]);

        expect($result['isError'])->toBeTrue()
            ->and($result['text'])->toContain('failed with 404');
    });

    test('reports Vito validation errors', function (): void {
        $result = $this->callTool('vito_create_worker', [...$this->serverIds(), 'name' => 'x']);

        expect($result['isError'])->toBeTrue()
            ->and($result['text'])->toContain('failed with 422')
            ->and($result['text'])->toContain('command');
    });

    test('rejects unknown tools and non-object arguments', function (): void {
        $this->mcp('tools/call', ['name' => 'vito_nope'])->assertJsonPath('error.code', -32602);
        $this->mcp('tools/call', ['name' => 'vito_health', 'arguments' => [1, 2]])->assertJsonPath('error.code', -32602);
    });

    test('keeps working across several calls in one request cycle', function (): void {
        expect($this->callTool('vito_get_server', $this->serverIds())['isError'])->toBeFalse()
            ->and($this->callTool('vito_list_sites', $this->serverIds())['isError'])->toBeFalse()
            ->and(request()->path())->toBe('api/mcp');
    });
});

describe('vito_create_site', function (): void {
    test('creates a laravel site with a php version and source control', function (): void {
        SSH::fake();
        Http::fake(['https://api.github.com/repos/*' => Http::response([], 201)]);
        $sourceControl = SourceControl::factory()->create(['provider' => Github::id(), 'user_id' => $this->user->id]);

        $listed = $this->callTool('vito_list_source_controls', ['project_id' => $this->server->project_id]);
        expect(array_column($listed['data']['data'], 'id'))->toContain($sourceControl->id);

        $result = $this->callTool('vito_create_site', [
            ...$this->serverIds(),
            'type' => 'laravel',
            'domain' => 'mcp.example.com',
            'user' => 'mcp',
            'php_version' => '8.2',
            'source_control' => $sourceControl->id,
            'repository' => 'org/repo',
            'branch' => 'main',
            'web_directory' => 'public',
            'composer' => true,
        ]);

        expect($result['isError'])->toBeFalse($result['text'])
            ->and($result['data']['domain'])->toBe('mcp.example.com')
            ->and(Site::query()->where('domain', 'mcp.example.com')->value('source_control_id'))->toBe($sourceControl->id);
    });
});

describe('vito_get_site_env', function (): void {
    test('returns key names, never values', function (): void {
        SSH::fake("APP_KEY=base64:secret\nDB_PASSWORD=hunter2");

        $result = $this->callTool('vito_get_site_env', [...$this->serverIds(), 'site_id' => $this->site->id]);

        expect($result['isError'])->toBeFalse()
            ->and($result['data'])->toBe(['keys' => ['APP_KEY', 'DB_PASSWORD']])
            ->and($result['text'])->not->toContain('secret')
            ->and($result['text'])->not->toContain('hunter2');
    });

    test('parses keys from the raw file when there are no variables', function (): void {
        expect(EnvKeys::from(['env' => "# comment\nAPP_NAME=Corpus\n\nexport QUEUE=redis\n  MAIL.HOST = x\nnot a var"]))
            ->toBe(['APP_NAME', 'QUEUE', 'MAIL.HOST'])
            ->and(EnvKeys::from([]))->toBe([]);
    });
});

describe('vito_set_site_env_vars', function (): void {
    test('changes, adds and removes variables and keeps the rest', function (): void {
        $ssh = SSH::fake("APP_NAME=Old\nDB_PASSWORD=hunter2\nQUEUE=sync");
        $this->site->update(['env_variables' => ['DB_PASSWORD']]);

        $result = $this->callTool('vito_set_site_env_vars', [
            ...$this->serverIds(),
            'site_id' => $this->site->id,
            'set' => [['key' => 'APP_NAME', 'value' => 'New'], ['key' => 'CACHE_STORE', 'value' => 'redis']],
            'unset' => ['QUEUE'],
        ]);

        expect($result['isError'])->toBeFalse($result['text'])
            ->and($result['data'])->toBe(['keys' => ['APP_NAME', 'DB_PASSWORD', 'CACHE_STORE']])
            ->and($result['text'])->not->toContain('hunter2')
            ->and($ssh->getUploadedContent())->toBe("APP_NAME=New\nDB_PASSWORD=hunter2\nCACHE_STORE=redis")
            ->and($this->site->refresh()->env_variables)->toBe(['DB_PASSWORD']);
    });

    test('needs something to change', function (): void {
        $result = $this->callTool('vito_set_site_env_vars', [...$this->serverIds(), 'site_id' => $this->site->id]);

        expect($result['isError'])->toBeTrue();
    });
});

describe('vito_create_database', function (): void {
    test('links an existing user given only existing_user_id', function (): void {
        SSH::fake();
        $user = DatabaseUser::factory()->create(['server_id' => $this->server->id, 'username' => 'app', 'databases' => []]);

        $result = $this->callTool('vito_create_database', [
            ...$this->serverIds(),
            'name' => 'app',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'existing_user_id' => $user->id,
        ]);

        expect($result['isError'])->toBeFalse($result['text'])
            ->and($user->refresh()->databases)->toContain('app');
    });

    test('rejects combinations Vito would half-apply', function (array $arguments): void {
        $result = $this->callTool('vito_create_database', [
            ...$this->serverIds(),
            'name' => 'app',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            ...$arguments,
        ]);

        expect($result['isError'])->toBeTrue()
            ->and(Database::query()->where('name', 'app')->exists())->toBeFalse();
    })->with([
        'user and existing user' => [['existing_user_id' => 1, 'username' => 'app', 'password' => 'secret123']],
        'username without password' => [['username' => 'app']],
    ]);
});

describe('vito_update_worker', function (): void {
    test('changes only the given fields', function (): void {
        SSH::fake();
        Bus::fake();
        $worker = Worker::factory()->create([
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
            'name' => 'horizon',
            'command' => 'php artisan horizon',
            'user' => 'vito',
            'numprocs' => 1,
            'auto_start' => true,
            'auto_restart' => false,
        ]);

        $result = $this->callTool('vito_update_worker', [...$this->serverIds(), 'worker_id' => $worker->id, 'numprocs' => 3]);

        expect($result['isError'])->toBeFalse($result['text']);
        $worker->refresh();
        expect($worker->numprocs)->toBe(3)
            ->and($worker->name)->toBe('horizon')
            ->and($worker->command)->toBe('php artisan horizon')
            ->and($worker->user)->toBe('vito')
            ->and((bool) $worker->auto_start)->toBeTrue()
            ->and((bool) $worker->auto_restart)->toBeFalse()
            ->and($worker->site_id)->toBe($this->site->id);
    });
});

describe('action tools', function (): void {
    test('vito_worker_action calls the matching route', function (): void {
        SSH::fake();
        Bus::fake();
        $worker = Worker::factory()->create(['server_id' => $this->server->id, 'status' => WorkerStatus::STOPPED]);

        $result = $this->callTool('vito_worker_action', [...$this->serverIds(), 'worker_id' => $worker->id, 'action' => 'start']);

        expect($result['isError'])->toBeFalse($result['text'])
            ->and($worker->refresh()->status)->toBe(WorkerStatus::STARTING);
    });

    test('vito_create_cron_job uses the site route when site_id is given', function (): void {
        SSH::fake();
        Bus::fake();

        $result = $this->callTool('vito_create_cron_job', [
            ...$this->serverIds(),
            'site_id' => $this->site->id,
            'command' => 'php artisan schedule:run',
            'user' => $this->site->user,
            'frequency' => '* * * * *',
        ]);

        expect($result['isError'])->toBeFalse($result['text'])
            ->and(CronJob::query()->where('command', 'php artisan schedule:run')->value('site_id'))->toBe($this->site->id);
    });
});

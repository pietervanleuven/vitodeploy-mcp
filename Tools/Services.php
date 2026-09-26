<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolContext;
use Illuminate\Support\Arr;

final class Services
{
    private const SERVICE_ACTIONS = ['start', 'stop', 'restart', 'reload', 'enable', 'disable'];

    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_services')
                ->title('List services')
                ->readOnly()
                ->description('List all services installed on a server (nginx, php, mysql, redis, supervisor, ...) with status.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.services'),

            Tool::make('vito_service_action')
                ->title('Manage service')
                ->disruptive()
                ->description('Start, stop, restart, reload, enable or disable a service on a server. stop/restart interrupt the service — confirm with the user for production servers.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'service_id' => Arg::integer('Service ID (see vito_list_services)'),
                    'action' => Arg::enum(self::SERVICE_ACTIONS, 'What to do with the service'),
                ])
                ->handle(
                    fn (array $arguments, ToolContext $context) => $context->api->call(
                        'api.projects.servers.services.'.$arguments['action'],
                        Arr::except($arguments, 'action'),
                    ),
                    array_map(fn (string $action) => 'api.projects.servers.services.'.$action, self::SERVICE_ACTIONS),
                ),
        ];
    }
}

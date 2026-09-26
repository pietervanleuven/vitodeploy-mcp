<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolContext;
use Illuminate\Support\Arr;

final class Workers
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_workers')
                ->title('List workers')
                ->readOnly()
                ->description('List all workers (supervisor processes, e.g. queue workers) on a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.workers'),

            Tool::make('vito_list_site_workers')
                ->title('List site workers')
                ->readOnly()
                ->description('List the workers attached to one site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.sites.workers'),

            Tool::make('vito_create_worker')
                ->title('Create worker')
                ->nonDestructive()
                ->description('Create a worker (supervisor-managed process) on a server, optionally attached to a site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'name' => Arg::string('Worker name'),
                    'command' => Arg::string('Command to run, e.g. php artisan queue:work'),
                    'user' => Arg::string('Linux user to run as'),
                    'auto_start' => Arg::boolean('Start automatically on boot'),
                    'auto_restart' => Arg::boolean('Restart automatically if it exits'),
                    'numprocs' => Arg::integer('Number of processes'),
                    'site_id' => Arg::optional(Arg::integer('Attach the worker to a site')),
                ])
                ->route('api.projects.servers.workers.create'),

            Tool::make('vito_update_worker')
                ->title('Update worker')
                ->destructive()
                ->description('Change a worker\'s settings. Omitted fields keep their current value. Workers managed by a site (site bootstrap workers) cannot be edited.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'worker_id' => Arg::integer('Worker ID (see vito_list_workers)'),
                    'name' => Arg::optional(Arg::string('Worker name')),
                    'command' => Arg::optional(Arg::string('Command to run')),
                    'user' => Arg::optional(Arg::string('Linux user to run as')),
                    'auto_start' => Arg::optional(Arg::boolean('Start automatically on boot')),
                    'auto_restart' => Arg::optional(Arg::boolean('Restart automatically if it exits')),
                    'numprocs' => Arg::optional(Arg::integer('Number of processes')),
                ])
                ->handle(
                    function (array $arguments, ToolContext $context) {
                        // Vito's update endpoint requires every field, so fill
                        // the ones not given from the current worker. site_id is
                        // left out: Vito keeps the worker's site when it is absent.
                        $ids = Arr::only($arguments, ['project_id', 'server_id', 'worker_id']);
                        $current = $context->api->call('api.projects.servers.workers.show', $ids);
                        $current = $current['data'] ?? $current;

                        return $context->api->call('api.projects.servers.workers.update', [
                            ...$ids,
                            ...Arr::only($current, ['name', 'command', 'user', 'auto_start', 'auto_restart', 'numprocs']),
                            ...Arr::except($arguments, array_keys($ids)),
                        ]);
                    },
                    ['api.projects.servers.workers.show', 'api.projects.servers.workers.update'],
                ),

            Tool::make('vito_delete_worker')
                ->title('Delete worker')
                ->destructive()
                ->description('Delete a worker from a server. Confirm with the user before calling.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'worker_id' => Arg::integer('Worker ID (see vito_list_workers)'),
                ])
                ->route('api.projects.servers.workers.delete'),

            Tool::make('vito_worker_action')
                ->title('Start/restart worker')
                ->nonDestructive()
                ->description('Start or restart a worker.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'worker_id' => Arg::integer('Worker ID (see vito_list_workers)'),
                    'action' => Arg::enum(['start', 'restart'], 'What to do with the worker'),
                ])
                ->handle(
                    fn (array $arguments, ToolContext $context) => $context->api->call(
                        'api.projects.servers.workers.'.$arguments['action'],
                        Arr::except($arguments, 'action'),
                    ),
                    ['api.projects.servers.workers.start', 'api.projects.servers.workers.restart'],
                ),

            Tool::make('vito_get_worker_logs')
                ->title('Get worker logs')
                ->readOnly()
                ->description('Get the log output of a worker.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'worker_id' => Arg::integer('Worker ID (see vito_list_workers)'),
                ])
                ->route('api.projects.servers.workers.logs'),
        ];
    }
}

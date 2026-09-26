<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolContext;

final class CronJobs
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_cron_jobs')
                ->title('List cron jobs')
                ->readOnly()
                ->description('List all cron jobs on a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.cron-jobs'),

            Tool::make('vito_list_site_cron_jobs')
                ->title('List site cron jobs')
                ->readOnly()
                ->description('List the cron jobs attached to one site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.sites.cron-jobs'),

            Tool::make('vito_create_cron_job')
                ->title('Create cron job')
                ->nonDestructive()
                ->description('Create a cron job on a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'command' => Arg::string('Command to run'),
                    'user' => Arg::string('Linux user to run as'),
                    'frequency' => Arg::string('Frequency: a cron expression, or a preset like \'* * * * *\', hourly/daily depending on Vito UI presets. Use \'custom\' plus the custom field for arbitrary expressions.'),
                    'custom' => Arg::optional(Arg::string('Custom cron expression when frequency is \'custom\'')),
                    'name' => Arg::optional(Arg::string('Display name')),
                    'site_id' => Arg::optional(Arg::integer('Attach the cron job to a site')),
                ])
                ->handle(
                    // Vito has a separate route for cron jobs attached to a site.
                    fn (array $arguments, ToolContext $context) => $context->api->call(
                        isset($arguments['site_id']) ? 'api.projects.servers.sites.cron-jobs.create' : 'api.projects.servers.cron-jobs.create',
                        $arguments,
                    ),
                    ['api.projects.servers.cron-jobs.create', 'api.projects.servers.sites.cron-jobs.create'],
                ),

            Tool::make('vito_delete_cron_job')
                ->title('Delete cron job')
                ->destructive()
                ->description('Delete a cron job from a server. Confirm with the user before calling.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'cron_job_id' => Arg::integer('Cron job ID (see vito_list_cron_jobs)'),
                ])
                ->route('api.projects.servers.cron-jobs.delete'),
        ];
    }
}

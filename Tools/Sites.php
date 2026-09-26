<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolContext;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Support\EnvKeys;

final class Sites
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_sites')
                ->title('List sites')
                ->readOnly()
                ->description('List all sites on a server, including domain, type, repository and status.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.sites'),

            Tool::make('vito_get_site')
                ->title('Get site')
                ->readOnly()
                ->description('Get details of a single site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                ])
                ->route('api.projects.servers.sites.show'),

            Tool::make('vito_create_site')
                ->title('Create site')
                ->nonDestructive()
                ->description('Create a new site on a server. Type is e.g. \'laravel\', \'php\', \'php-blank\', \'phpmyadmin\', \'wordpress\' or \'load-balancer\' depending on the Vito instance\'s enabled site types.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'type' => Arg::string('Site type, e.g. laravel, php, wordpress, load-balancer'),
                    'domain' => Arg::string('Primary domain of the site'),
                    'user' => Arg::string('Linux user the site runs as'),
                    'aliases' => Arg::optional(Arg::array(['type' => 'string'], 'Additional domains')),
                    'repository' => Arg::optional(Arg::string('Git repository, e.g. org/repo')),
                    'branch' => Arg::optional(Arg::string('Git branch to deploy')),
                    'source_control_id' => Arg::optional(Arg::integer('Source control connection ID')),
                    'node_version' => Arg::optional(Arg::enum(['none', '22', '23', '24'], '')),
                    'bun_version' => Arg::optional(Arg::enum(['none', '1.0', '1.1', '1.2'], '')),
                ])
                ->route('api.projects.servers.sites.create'),

            Tool::make('vito_delete_site')
                ->title('Delete site')
                ->destructive()
                ->description('Permanently delete a site from a server. Irreversible — confirm with the user before calling.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                ])
                ->route('api.projects.servers.sites.delete'),

            Tool::make('vito_deploy_site')
                ->title('Deploy site')
                ->nonDestructive()
                ->description('Trigger a deployment for a site using its deployment script.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                ])
                ->route('api.projects.servers.sites.deploy'),

            Tool::make('vito_list_deployments')
                ->title('List deployments')
                ->readOnly()
                ->description('List deployments of a site with their status.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.sites.deployments'),

            Tool::make('vito_get_deployment')
                ->title('Get deployment')
                ->readOnly()
                ->description('Get a single deployment of a site, including its log output.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'deployment_id' => Arg::integer('Deployment ID (see vito_list_deployments)'),
                ])
                ->route('api.projects.servers.sites.deployments.show'),

            Tool::make('vito_get_deployment_script')
                ->title('Get deployment script')
                ->readOnly()
                ->description('Get the deployment script of a site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                ])
                ->route('api.projects.servers.sites.deployment-script.show'),

            Tool::make('vito_update_deployment_script')
                ->title('Update deployment script')
                ->destructive()
                ->description('Replace the deployment script of a site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'script' => Arg::string('Full deployment script content'),
                    'restart_workers' => Arg::optional(Arg::boolean('Restart site workers after deployments')),
                ])
                ->route('api.projects.servers.sites.deployment-script'),

            Tool::make('vito_get_site_env')
                ->title('List site .env keys')
                ->readOnly()
                ->description('List which variables are set in a site\'s .env file. Returns key names only, never values, so secrets are not exposed.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                ])
                ->handle(
                    function (array $arguments, ToolContext $context) {
                        // Key names only: the raw .env and its values may hold secrets.
                        $response = $context->api->call('api.projects.servers.sites.env.show', $arguments);

                        return ['keys' => EnvKeys::from($response['data'] ?? [])];
                    },
                    ['api.projects.servers.sites.env.show'],
                ),

            Tool::make('vito_update_site_env')
                ->title('Update site .env')
                ->destructive()
                ->description('Replace the environment (.env) file content of a site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'env' => Arg::string('Full .env file content'),
                ])
                ->route('api.projects.servers.sites.env'),

            Tool::make('vito_update_web_directory')
                ->title('Update web directory')
                ->destructive()
                ->description('Set the directory, relative to the site path, that the web server serves (e.g. public, or current/public for zero-downtime layouts). Regenerates the vhost.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'web_directory' => Arg::string('Path relative to the site path; empty string serves the site path itself'),
                ])
                ->route('api.projects.servers.sites.web-directory'),

            Tool::make('vito_enable_ssl')
                ->title('Enable SSL')
                ->nonDestructive()
                ->description('Enable SSL (force HTTPS) for a site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                ])
                ->route('api.projects.servers.sites.enable-ssl'),

            Tool::make('vito_disable_ssl')
                ->title('Disable SSL')
                ->disruptive()
                ->description('Disable SSL (force HTTPS) for a site.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                ])
                ->route('api.projects.servers.sites.disable-ssl'),

            Tool::make('vito_list_ssls')
                ->title('List SSL certificates')
                ->readOnly()
                ->description('List SSL certificates on a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.ssls'),
        ];
    }
}

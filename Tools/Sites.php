<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolContext;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolError;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Support\EnvKeys;
use Illuminate\Support\Arr;

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
                ->description('Create a new site on a server. Type is e.g. \'laravel\', \'php\', \'php-blank\', \'phpmyadmin\', \'wordpress\' or \'load-balancer\' depending on the Vito instance\'s enabled site types. Laravel and PHP sites need php_version, source_control, repository and branch; php-blank and phpmyadmin need php_version; Node, Bun and other proxied sites need source_control, repository, branch and port.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'type' => Arg::string('Site type, e.g. laravel, php, wordpress, load-balancer'),
                    'domain' => Arg::string('Primary domain of the site'),
                    'user' => Arg::string('Linux user the site runs as'),
                    'aliases' => Arg::optional(Arg::array(['type' => 'string'], 'Additional domains')),
                    'php_version' => Arg::optional(Arg::string('PHP version installed on the server, e.g. 8.4 (see vito_list_services)')),
                    'source_control' => Arg::optional(Arg::integer('Source control connection ID (see vito_list_source_controls)')),
                    'repository' => Arg::optional(Arg::string('Git repository, e.g. org/repo')),
                    'branch' => Arg::optional(Arg::string('Git branch to deploy')),
                    'web_directory' => Arg::optional(Arg::string('Directory relative to the site path that the web server serves, e.g. public')),
                    'composer' => Arg::optional(Arg::boolean('Run composer install when the site is created')),
                    'port' => Arg::optional(Arg::integer('Port the app listens on, for proxied sites (1024-65535)')),
                    'start_command' => Arg::optional(Arg::string('Command that starts the app, for proxied sites')),
                    'package_manager' => Arg::optional(Arg::enum(['none', 'node', 'pnpm', 'yarn'], 'JavaScript package manager to install')),
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
                ->description('Replace the whole environment (.env) file of a site. Any variable missing from env is removed, so to change or add individual variables use vito_set_site_env_vars instead.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'env' => Arg::string('Full .env file content'),
                ])
                ->route('api.projects.servers.sites.env'),

            Tool::make('vito_set_site_env_vars')
                ->title('Set site .env variables')
                ->destructive()
                ->description('Set, add or remove individual variables in a site\'s .env file. Every other variable keeps its current value, secrets included, without the values ever being read back. Comments and blank lines in the file are not kept. Returns the resulting key names.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'site_id' => Arg::siteId(),
                    'set' => Arg::optional(Arg::array([
                        'type' => 'object',
                        'properties' => [
                            'key' => Arg::string('Variable name'),
                            'value' => Arg::string('New value'),
                            'is_secret' => Arg::boolean('Mark as secret, so Vito masks it (default: keep the current marking, or false for a new key)'),
                        ],
                        'required' => ['key', 'value'],
                        'additionalProperties' => false,
                    ], 'Variables to change or add')),
                    'unset' => Arg::optional(Arg::array(['type' => 'string'], 'Names of variables to remove')),
                ])
                ->handle(
                    function (array $arguments, ToolContext $context) {
                        $ids = Arr::only($arguments, ['project_id', 'server_id', 'site_id']);
                        $set = array_column($arguments['set'] ?? [], null, 'key');
                        $unset = array_flip($arguments['unset'] ?? []);
                        if ($set === [] && $unset === []) {
                            throw new ToolError('Pass at least one variable in set or unset.');
                        }

                        // Vito writes exactly the variables it is sent, so send
                        // every current one. Secrets arrive masked as an empty
                        // value, which Vito restores from the live file on write.
                        $current = $context->api->call('api.projects.servers.sites.env.show', $ids)['data']['variables'] ?? [];
                        $variables = [];
                        foreach ($current as $variable) {
                            $key = $variable['key'];
                            if (isset($unset[$key])) {
                                continue;
                            }
                            if (isset($set[$key])) {
                                $variable = ['is_secret' => $variable['is_secret'], ...$set[$key]];
                                unset($set[$key]);
                            }
                            $variables[] = $variable;
                        }
                        foreach ($set as $variable) {
                            $variables[] = ['is_secret' => false, ...$variable];
                        }

                        if ($variables === []) {
                            throw new ToolError('This would leave the .env file empty; use vito_update_site_env to replace the file instead.');
                        }

                        $context->api->call('api.projects.servers.sites.env', [...$ids, 'variables' => $variables]);

                        return ['keys' => array_column($variables, 'key')];
                    },
                    ['api.projects.servers.sites.env.show', 'api.projects.servers.sites.env'],
                ),

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

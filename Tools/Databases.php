<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolContext;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolError;

final class Databases
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_databases')
                ->title('List databases')
                ->readOnly()
                ->description('List all databases on a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.databases'),

            Tool::make('vito_create_database')
                ->title('Create database')
                ->nonDestructive()
                ->description('Create a database on a server. To give a user access in the same call, pass either username + password to create a new user with admin permission on it, or existing_user_id to link an existing database user (see vito_list_database_users). Without either, no user gets access: link one later with vito_link_database_user.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'name' => Arg::string('Database name'),
                    'charset' => Arg::string('Charset, e.g. utf8mb4'),
                    'collation' => Arg::string('Collation, e.g. utf8mb4_unicode_ci'),
                    'username' => Arg::optional(Arg::string('Create a new database user with this username (with password)')),
                    'password' => Arg::optional(Arg::string('Password for the new user, at least 6 characters (with username)')),
                    'existing_user_id' => Arg::optional(Arg::integer('Link this existing database user instead of creating one')),
                ])
                ->handle(
                    function (array $arguments, ToolContext $context) {
                        // Vito only links existing_user_id when `user` is true, and
                        // silently ignores it otherwise; `user` without it is a 422.
                        // Derive the flag so neither can happen.
                        if (isset($arguments['existing_user_id'])) {
                            if (isset($arguments['username']) || isset($arguments['password'])) {
                                throw new ToolError('Pass either existing_user_id or username + password, not both.');
                            }
                            $arguments['user'] = true;
                        }

                        // Vito creates the database before it validates the new
                        // user, so catch a missing password up front.
                        if (isset($arguments['username']) !== isset($arguments['password'])) {
                            throw new ToolError('username and password must be given together.');
                        }

                        return $context->api->call('api.projects.servers.databases.create', $arguments);
                    },
                    ['api.projects.servers.databases.create'],
                ),

            Tool::make('vito_delete_database')
                ->title('Delete database')
                ->destructive()
                ->description('Permanently delete a database and its data. Irreversible — confirm with the user before calling.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'database_id' => Arg::integer('Database ID (see vito_list_databases)'),
                ])
                ->route('api.projects.servers.databases.delete'),

            Tool::make('vito_list_database_users')
                ->title('List database users')
                ->readOnly()
                ->description('List all database users on a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.database-users'),

            Tool::make('vito_create_database_user')
                ->title('Create database user')
                ->nonDestructive()
                ->description('Create a database user on a server. The user has access to no database until linked with vito_link_database_user.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'username' => Arg::string('Username'),
                    'password' => Arg::string('Password, at least 6 characters'),
                    'permission' => Arg::optional(Arg::enum(['read', 'write', 'admin'], 'Permission on linked databases (default admin)')),
                    'remote' => Arg::optional(Arg::boolean('Allow connections from other hosts; host defaults to % (any host)')),
                    'host' => Arg::optional(Arg::string('Host the user may connect from, e.g. 10.0.0.% (default localhost, or % when remote=true)')),
                ])
                ->route('api.projects.servers.database-users.create'),

            Tool::make('vito_link_database_user')
                ->title('Link database user')
                ->destructive()
                ->description('Set which databases a database user has access to (replaces the current links).')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'database_user_id' => Arg::integer('Database user ID (see vito_list_database_users)'),
                    'databases' => Arg::array(['type' => 'string'], 'Database names the user should have access to'),
                ])
                ->route('api.projects.servers.database-users.link'),
        ];
    }
}

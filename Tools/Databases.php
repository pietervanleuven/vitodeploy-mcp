<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;

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
                ->description('Create a database on a server, optionally with a new user that gets access to it.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'name' => Arg::string('Database name'),
                    'charset' => Arg::string('Charset, e.g. utf8mb4'),
                    'collation' => Arg::string('Collation, e.g. utf8mb4_unicode_ci'),
                    'user' => Arg::optional(Arg::boolean('Also create a database user')),
                    'username' => Arg::optional(Arg::string('Username for the new user (when user=true)')),
                    'password' => Arg::optional(Arg::string('Password for the new user (when user=true)')),
                    'existing_user_id' => Arg::optional(Arg::integer('Link an existing database user instead')),
                ])
                ->route('api.projects.servers.databases.create'),

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
                ->description('Create a database user on a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'username' => Arg::string(''),
                    'password' => Arg::string(''),
                    'permission' => Arg::optional(Arg::enum(['read', 'write', 'admin'], '')),
                    'remote' => Arg::optional(Arg::boolean('Allow remote connections')),
                    'host' => Arg::optional(Arg::string('Remote host, e.g. % (when remote=true)')),
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

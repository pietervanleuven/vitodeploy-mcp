<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;

final class Servers
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_servers')
                ->title('List servers')
                ->readOnly()
                ->description('List all servers in a project, including status, IP, OS and installed services.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers'),

            Tool::make('vito_get_server')
                ->title('Get server')
                ->readOnly()
                ->description('Get details of a single server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                ])
                ->route('api.projects.servers.show'),

            Tool::make('vito_reboot_server')
                ->title('Reboot server')
                ->disruptive()
                ->description('Reboot a server. This interrupts everything running on it — confirm with the user before calling.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                ])
                ->route('api.projects.servers.reboot'),
        ];
    }
}

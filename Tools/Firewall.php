<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;

final class Firewall
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_firewall_rules')
                ->title('List firewall rules')
                ->readOnly()
                ->description('List all firewall rules of a server.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.firewall-rules'),

            Tool::make('vito_create_firewall_rule')
                ->title('Create firewall rule')
                ->disruptive()
                ->description('Create a firewall rule on a server. Careful: deny rules or wrong ports can lock you out — confirm with the user before calling.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'name' => Arg::string('Rule name'),
                    'type' => Arg::enum(['allow', 'deny'], ''),
                    'protocol' => Arg::enum(['tcp', 'udp'], ''),
                    'port' => Arg::string('Port or port range, e.g. \'443\' or \'6000:6100\''),
                    'source_any' => Arg::optional(Arg::boolean('Apply to any source (default true)')),
                    'source' => Arg::optional(Arg::string('Source IP when source_any=false')),
                    'mask' => Arg::optional(Arg::integer('Source subnet mask, e.g. 24')),
                ])
                ->route('api.projects.servers.firewall-rules.create'),

            Tool::make('vito_delete_firewall_rule')
                ->title('Delete firewall rule')
                ->destructive()
                ->description('Delete a firewall rule from a server. Confirm with the user before calling.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'firewall_rule_id' => Arg::integer('Firewall rule ID (see vito_list_firewall_rules)'),
                ])
                ->route('api.projects.servers.firewall-rules.delete'),
        ];
    }
}

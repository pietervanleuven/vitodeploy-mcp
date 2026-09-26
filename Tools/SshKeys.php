<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;

final class SshKeys
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_ssh_keys')
                ->title('List SSH keys')
                ->readOnly()
                ->description('List the SSH keys deployed to a server, with the server user each is deployed to.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.servers.ssh-keys'),

            Tool::make('vito_deploy_ssh_key')
                ->title('Deploy SSH key')
                ->nonDestructive()
                ->description('Add an SSH public key to a server user\'s authorized_keys. Pass key_id to deploy a key already in your Vito profile, or name + public_key to add a new key to the profile and deploy it in one call.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'server_id' => Arg::serverId(),
                    'key_id' => Arg::optional(Arg::integer('Existing SSH key ID from your Vito profile')),
                    'name' => Arg::optional(Arg::string('Name for a new key (with public_key)')),
                    'public_key' => Arg::optional(Arg::string('Public key content for a new key, e.g. ssh-ed25519 AAAA...')),
                    'user' => Arg::optional(Arg::string('Server user to deploy the key to (e.g. a site user). Defaults to the server\'s SSH user.')),
                ])
                ->route('api.projects.servers.ssh-keys.create'),
        ];
    }
}

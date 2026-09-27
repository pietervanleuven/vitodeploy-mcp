<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;

final class Projects
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_health')
                ->title('Vito health check')
                ->readOnly()
                ->description('Check that the VitoDeploy instance is reachable and return its version.')
                ->route('api.health'),

            Tool::make('vito_list_projects')
                ->title('List projects')
                ->readOnly()
                ->description('List all VitoDeploy projects. Most other tools need a project_id from this list.')
                ->route('api.projects.index'),

            Tool::make('vito_create_project')
                ->title('Create project')
                ->nonDestructive()
                ->description('Create a new VitoDeploy project.')
                ->input([
                    'name' => Arg::string('Project name'),
                ])
                ->route('api.projects.create'),

            Tool::make('vito_list_source_controls')
                ->title('List source controls')
                ->readOnly()
                ->description('List the source control connections (GitHub, GitLab, Bitbucket, …) usable in a project, for the source_control argument of vito_create_site.')
                ->input([
                    'project_id' => Arg::projectId(),
                ])
                ->route('api.projects.source-controls'),
        ];
    }
}

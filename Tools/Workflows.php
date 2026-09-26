<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Tools;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Arg;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;

final class Workflows
{
    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return [
            Tool::make('vito_list_workflows')
                ->title('List workflows')
                ->readOnly()
                ->description('List all workflows in a project.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.workflows'),

            Tool::make('vito_run_workflow')
                ->title('Run workflow')
                ->disruptive()
                ->description('Trigger a run of a workflow.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'workflow_id' => Arg::integer('Workflow ID (see vito_list_workflows)'),
                ])
                ->route('api.projects.workflows.runs.store'),

            Tool::make('vito_list_workflow_runs')
                ->title('List workflow runs')
                ->readOnly()
                ->description('List runs of a workflow with their status.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'workflow_id' => Arg::integer('Workflow ID (see vito_list_workflows)'),
                    'page' => Arg::page(),
                ])
                ->route('api.projects.workflows.runs'),

            Tool::make('vito_get_workflow_run_log')
                ->title('Get workflow run log')
                ->readOnly()
                ->description('Get the log output of a workflow run.')
                ->input([
                    'project_id' => Arg::projectId(),
                    'workflow_id' => Arg::integer('Workflow ID (see vito_list_workflows)'),
                    'workflow_run_id' => Arg::integer('Workflow run ID (see vito_list_workflow_runs)'),
                ])
                ->route('api.projects.workflows.runs.log'),
        ];
    }
}

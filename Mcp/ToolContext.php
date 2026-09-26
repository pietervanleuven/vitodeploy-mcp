<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Support\ApiDispatcher;

/**
 * What a tool handler gets besides its arguments.
 */
final class ToolContext
{
    public function __construct(public readonly ApiDispatcher $api) {}
}

<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp;

use RuntimeException;

/**
 * A failure to report back to the model as a tool result with isError: true,
 * rather than as a protocol error, so it can correct itself.
 */
class ToolError extends RuntimeException {}

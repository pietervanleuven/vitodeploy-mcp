# VitoDeploy MCP Server

A [VitoDeploy](https://vitodeploy.com) plugin that adds a [Model Context Protocol](https://modelcontextprotocol.io)
endpoint to your Vito panel, so Claude and other AI agents can manage your servers, sites, deployments, databases,
workers, cron jobs and firewall rules with your existing Vito API keys.

Once enabled, the endpoint lives at `https://<your-vito>/api/mcp`. There is nothing to install on the machine that runs
the agent.

Requires VitoDeploy **4.1** or later.

> [!NOTE]
> This is an early release. Tool names and arguments may still change before 1.0, so pin a version if you script
> against it.

## How it works

Every tool is a thin wrapper around one of Vito's own REST API routes. The plugin runs that route in-process, as the
caller, through the route's full middleware stack: Sanctum authentication, the `read`/`write` token abilities, project
scoping and the controller's policies and validation. A tool can therefore never do more than the same API key could do
over Vito's REST API, and the plugin contains no permission or validation logic of its own (a few tools only reject
argument combinations that Vito would silently ignore or half-apply).

The plugin runs no shell commands, makes no network requests and does not write to the database itself. Its only
additions are a route (`/api/mcp`) and the MCP protocol handling.

## Installation

1. In Vito, open **Admin → Plugins**, install **MCP Server** (or install it from this repository's GitHub URL) and
   enable it.
2. Create an API key under **Settings → API Keys**. Give it only what the agent needs:
   - `read` only: the agent sees the 25 read-only tools and nothing else.
   - `read` and `write`: all 51 tools.
   - Limit the key to one project if the agent only needs that project.

## Connecting a client

### Claude Code

```sh
claude mcp add --transport http vitodeploy https://vito.example.com/api/mcp \
  --header "Authorization: Bearer <your-api-key>"
```

### Other clients

Most clients that support remote MCP servers accept a configuration like this (for example `.mcp.json` or Claude
Desktop's `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "vitodeploy": {
      "type": "http",
      "url": "https://vito.example.com/api/mcp",
      "headers": {
        "Authorization": "Bearer <your-api-key>"
      }
    }
  }
}
```

The endpoint authenticates with a bearer token only. Clients that require OAuth for remote servers (such as custom
connectors on claude.ai) are not supported.

## Tools

- **Projects**: health check, list and create projects, list source control connections
- **Servers**: list, get, reboot
- **Sites**: list, get, create, delete, deploy, deployments, deployment script get/update, web directory, `.env`
  keys, replace the file or set/unset single variables, SSL enable/disable, list SSL certificates
- **Databases**: databases list/create/delete, database users list/create/link
- **Services**: list, start/stop/restart/reload/enable/disable
- **Workers**: list (server or site), create, update, delete, start/restart, logs
- **Cron jobs**: list (server or site), create, delete
- **Firewall**: list, create, delete
- **Workflows**: list, run, list runs, run logs
- **SSH keys**: list deployed keys, deploy a key to a server user

Every tool carries [MCP annotations](https://modelcontextprotocol.io/docs/concepts/tools#tool-annotations)
(`readOnlyHint`, `destructiveHint`, …), so clients can ask for confirmation before destructive actions.

Because the plugin only wraps Vito's REST API, anything that API does not offer is not available here either, for
example: listing or validating a site's domains, viewing the generated vhost, stopping a worker, enabling or disabling a
cron job, reading deployment logs, and the build script of a site with Modern Deployment enabled (the deployment
script tools act on its pre-flight script instead, and say so in their response).

## Security

- `.env` values never reach the model: `vito_get_site_env` returns variable names only, and `vito_set_site_env_vars`
  changes single variables without reading the others back.
- Browser requests from other origins are rejected, as the MCP specification requires for HTTP servers.
- Worker and workflow logs can contain anything your applications print. Treat them as sensitive and, in agentic use,
  as untrusted input.

## Protocol

MCP Streamable HTTP, stateless: each `POST` carries one JSON-RPC message and receives a JSON response. There are no
sessions and no server-sent event stream (`GET` and `DELETE` return 405). Supported protocol versions: `2025-11-25`,
`2025-06-18` and `2025-03-26`.

## Extending from another plugin

Other Vito plugins can add tools with `ToolRegistry::extend()`. Register a provider from your plugin's `boot()`; it is
called lazily on every request, so plugin boot order does not matter. Guard the call with `class_exists()` so your
plugin works without this one.

```php
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\Tool;
use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolRegistry;

if (class_exists(ToolRegistry::class)) {
    ToolRegistry::extend(fn () => [
        Tool::make('vito_list_widgets')
            ->title('List widgets')
            ->description('Lists the widgets of a server.')
            ->input(['server_id' => ['type' => 'integer'], 'project_id' => ['type' => 'integer']])
            ->readOnly()
            ->route('api.projects.servers.widgets.index'),
    ]);
}
```

The same rules apply as for core tools: every tool goes through a named Vito API route (so authentication, token
abilities, policies and validation stay Vito's own), and read-only tools are the only ones a `read`-only key sees. A tool
whose name is already taken, by a core tool or an earlier extension, is ignored and a warning is logged. A provider that
throws is skipped and logged, and never breaks the endpoint. Extension tools are not part of the tool counts above.
Tests can call `ToolRegistry::flushExtensions()` to reset the registry.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for how to add a tool, the commit conventions and how releases are made.

## License

MIT, see [LICENSE](LICENSE).

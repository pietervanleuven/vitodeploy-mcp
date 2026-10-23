# Changelog

## [0.4.1](https://github.com/pietervanleuven/vitodeploy-mcp/compare/v0.4.0...v0.4.1) (2026-10-22)


### Bug Fixes

* check tool arguments against the input schema ([3339d57](https://github.com/pietervanleuven/vitodeploy-mcp/commit/3339d57f18c5e5e3c3e09e5873ab41e7473575eb))
* report the server output when a Vito SSH command fails ([8c23b59](https://github.com/pietervanleuven/vitodeploy-mcp/commit/8c23b5928cad80661ab5824311f990e90e2d7b51))
* return tool results as compact JSON ([72c791d](https://github.com/pietervanleuven/vitodeploy-mcp/commit/72c791d0f15a411037472a4ed8b5a7655f903c0d))
* say where commands run under Modern Deployment and that PHP is not reloaded ([2f40483](https://github.com/pietervanleuven/vitodeploy-mcp/commit/2f404839ca62a0fd2890878ee522f49cd6c22b36))
* **sites:** list deployments with the commit subject only ([5497246](https://github.com/pietervanleuven/vitodeploy-mcp/commit/54972468d2ce2cadfb41a8dca737c419154c504d))

## [0.4.0](https://github.com/pietervanleuven/vitodeploy-mcp/compare/v0.3.0...v0.4.0) (2026-09-29)


### Features

* let other plugins register MCP tools ([#11](https://github.com/pietervanleuven/vitodeploy-mcp/issues/11)) ([0797113](https://github.com/pietervanleuven/vitodeploy-mcp/commit/07971135a504973c4c580b76935f99493f87e93d))


### Bug Fixes

* validate malformed MCP requests ([b5b12aa](https://github.com/pietervanleuven/vitodeploy-mcp/commit/b5b12aae6c129a27432aa1789d8497f46104b149))

## [0.3.0](https://github.com/pietervanleuven/vitodeploy-mcp/compare/v0.2.0...v0.3.0) (2026-09-27)


### Features

* **sites:** add vito_set_site_env_vars to change single .env variables ([4904da1](https://github.com/pietervanleuven/vitodeploy-mcp/commit/4904da1819809ed5b9706fdda146609ec582b6c2)), closes [#3](https://github.com/pietervanleuven/vitodeploy-mcp/issues/3)


### Bug Fixes

* correct firewall rule and cron job argument descriptions ([01bd685](https://github.com/pietervanleuven/vitodeploy-mcp/commit/01bd6852187a61bf6b6f80b45ca6a1fdb6f4bfe0))
* **databases:** link existing_user_id without the user flag ([50eb5b3](https://github.com/pietervanleuven/vitodeploy-mcp/commit/50eb5b3a5dec6ade3f7a836ce0b3d59c7c457153)), closes [#2](https://github.com/pietervanleuven/vitodeploy-mcp/issues/2)
* **sites:** say which deployment script the script tools act on ([345d9c0](https://github.com/pietervanleuven/vitodeploy-mcp/commit/345d9c09da324a6c280194dbba6038ed11bf5d27)), closes [#5](https://github.com/pietervanleuven/vitodeploy-mcp/issues/5)
* **sites:** stop promising deployment log output ([163dcde](https://github.com/pietervanleuven/vitodeploy-mcp/commit/163dcdec22bead06f6138d84cc244cc9ec3e34c6)), closes [#4](https://github.com/pietervanleuven/vitodeploy-mcp/issues/4)

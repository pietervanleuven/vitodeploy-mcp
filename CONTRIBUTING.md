# Contributing

Thanks for helping out. Bug reports and pull requests are welcome. For a larger change, open an issue first so we can
agree on the approach.

## Adding or changing a tool

Tools live in `Tools/`, one class per area. Each tool maps onto one or more of Vito's named REST API routes:

- **Plain pass-through:** `->route('api....')`. The arguments go to the route unchanged: `*_id` arguments fill the
  route parameters, and the rest become the query string (GET) or the JSON body.
- **Anything more:** `->handle(fn, [routes])`, calling `$context->api->call()` for each route and listing every route
  it uses. Throw `ToolError` for input the model should correct.
- **Annotations:** pick one of `readOnly()`, `nonDestructive()`, `destructive()` or `disruptive()`. Read-only tools
  are the only ones a `read`-only API key sees.
- **Descriptions:** check them against Vito's controller and action code, not the UI. Describe what the tool actually
  returns and when an argument is ignored. Agents act on every word.
- **Boundaries:** never call Vito models or actions directly. Going through the API routes is what keeps
  authentication, token abilities, project scoping, policies and validation Vito's own.

When the number of tools changes, update the counts in `tests/Feature/ToolsTest.php` and in the README (Installation
and Tools sections). Add a test in `tests/Feature/ToolsTest.php` for any tool with its own handler.

## Commits and pull requests

Commit messages and PR titles follow [Conventional Commits](https://www.conventionalcommits.org):

| Type | Use for | Release |
| --- | --- | --- |
| `feat` | a new tool, argument or capability | minor |
| `fix` | a bug, or a tool description that is wrong | patch |
| `feat!` / `fix!` | a breaking change, such as removing or renaming a tool or argument | minor while below 1.0 |
| `docs`, `test`, `ci`, `refactor`, `chore` | everything else | none |

Use the `Tools/` area as the scope where it fits: `fix(databases): …`, `feat(sites): …`.

PRs are squash-merged, and the PR title becomes the commit message on `main`, so a check rejects titles that don't
follow the format. Put `Fixes #123` in the PR description to close an issue on merge.

## Releases

[release-please](https://github.com/googleapis/release-please) keeps a release PR open. It holds the next version, the
`composer.json` version bump and the `CHANGELOG.md` entry, all built from the commits on `main`. Merging it tags the
release and publishes the GitHub release that Vito installs plugins from. Don't edit the version or the changelog by
hand.

# Craft Console CLI

A command-line client for [Craft Console](https://console.craftcms.com). List and look up the Craft and plugin licenses you or your organizations own, find renewals coming due, and issue or update licenses for plugins you develop.

It talks to the [Craftnet API](https://docs.craftcms.com/craftnet/), the API behind Craft Console. The binary is called `craft-console` so it doesn't clash with a Craft project's own `./craft` script.

> [!NOTE]
> This is an unofficial tool built by [Reusser](https://reusser.com). It isn't made, endorsed or supported by Pixel & Tonic. Craft, Craft CMS and Craft Console are their trademarks.

## Requirements

- PHP 8.4.1 or later
- Composer

## Installation

```bash
composer global require reusserdesign/craft-console
```

Make sure Composer's global `bin` directory (usually `~/.composer/vendor/bin`) is on your `PATH`. The CLI is a single self-contained phar, so it doesn't share or upgrade dependencies with your other global Composer tools.

If a Craft site you work in is pinned to an older PHP version, run the CLI through your PHP version manager. With [Butler](https://getbutler.dev), for example, use `butler craft-console`.

## Authentication

Generate an API key in Craft Console under **Settings → Profile**. The key is only shown once, and generating a new one invalidates the old one.

```bash
craft-console login
```

You'll be asked for your Craft Console username (or email) and API key. You can also pass them as options:

```bash
craft-console login --username=you@example.com --key=your-api-key
```

The credentials are checked before they're saved to `~/.craft-console/config.json`, which only your user can read. `craft-console logout` deletes that file.

### Environment variables

| Variable | Purpose |
| --- | --- |
| `CRAFT_CONSOLE_USERNAME` | Username or email. Used together with `CRAFT_CONSOLE_API_KEY`. |
| `CRAFT_CONSOLE_API_KEY` | API key. Used together with `CRAFT_CONSOLE_USERNAME`. |
| `CRAFT_CONSOLE_ORG` | Organization slug to work within, or `personal` for your own account. |
| `CRAFT_CONSOLE_CONFIG` | Path to the config file, instead of `~/.craft-console/config.json`. |

Each variable can be exported in your shell profile or set in the `.env` file of the Craft project you're working in. Exported variables win, then the project's `.env`, then whatever `login` and `org:switch` saved. So a client site can carry its own organization:

```bash
# ~/Sites/client-site/.env
CRAFT_CONSOLE_ORG=client-org
```

The username and API key only take effect as a pair, so setting one without the other falls back to your saved login. `CRAFT_CONSOLE_CONFIG` is only read from your shell. Setting the credentials as variables lets you use the CLI in CI without running `login`. Keep them out of any `.env` file that gets committed.

## Organizations

By default, license commands use your personal account. To work with an organization's licenses, switch to it by its slug (the one in its Craft Console URL):

```bash
craft-console org:switch acme
craft-console org:current
craft-console org:switch --personal
```

The Craftnet API can't list your organizations, so `org:switch` checks that you have access to the slug you give it. `org:list` shows the organizations you've switched to before.

Any license command also accepts `--org=<slug>` or `--personal` to override the current organization for a single run, and `CRAFT_CONSOLE_ORG` (see above) sets it per shell or per project. `org:current` shows which one is in effect and where it came from.

## Commands

### The current project's license

Run `license` from anywhere inside a Craft project, or pass the project's path:

```bash
cd ~/Sites/my-craft-site
craft-console license
craft-console license ~/Sites/other-site
```

It finds the project's license key the same way Craft does: the `CRAFT_LICENSE_KEY` environment variable (from your shell or the project's `.env`), then the file at `CRAFT_LICENSE_KEY_PATH`, then `config/license.key`. It looks the key up in your personal account, the current organization and every organization you've switched to, then shows:

- the edition, domain, owner, renewal price and auto-renew setting
- the installed Craft version (from `composer.lock`) and the edition in the project config
- the **latest eligible version**: the newest stable Craft release published before the license's updates expired, which is as far as the site can update without renewing
- a breakdown for each major version from the installed one up (a Craft 3 site sees 3.x, 4.x and 5.x), with the latest eligible release, the latest release, and how many releases need a renewal
- a warning if the installed version is already past what the license allows, or if the project's edition doesn't match the license
- every plugin in the project config: its installed version and edition, when its license's updates end, the latest release its license covers in the installed major version, the latest release overall, and its renewal price. Plugin license keys stored as `$ENV_VAR` references are read from the project's `.env`.
- warnings for plugins whose license is missing from your accounts, is a trial, is for a different edition, or is attached to a different Craft license
- the yearly cost of renewing every license found, and what it costs to renew the ones that have lapsed

Plugin licenses are searched in the account that owns the Craft license first. Plugins that aren't on Packagist (private packages) show as such. Use `--no-plugins` to skip them.

```bash
craft-console license --json   # everything above, for scripts
craft-console license --key    # print the project's license key (no API call)
```

### Craft licenses

```bash
craft-console cms:list
craft-console cms:list --search=acme --edition=pro,team
craft-console cms:list --expiring=30
craft-console cms:show 12345
craft-console cms:show example.com
craft-console cms:show example.com --key
```

`cms:show` accepts a license ID, key or domain. `--key` prints only the license key, which is handy for scripts:

```bash
echo "CRAFT_LICENSE_KEY=$(craft-console cms:show example.com --key)" >> .env
```

### Plugin licenses

```bash
craft-console plugin:list
craft-console plugin:list --plugin=seomatic --trials
craft-console plugin:show 67890
craft-console plugin:show ABCDEFGHIJKLMNOPQRSTUVWX --key
```

### List options

`cms:list`, `plugin:list` and `developer:list` all accept these options:

| Option | Description |
| --- | --- |
| `--search=` | Case-insensitive search of keys, emails, notes and domains |
| `--edition=` | Comma-delimited edition handles |
| `--before=` / `--after=` | Only licenses created before or after a UTC datetime |
| `--sort=` | Sort field (`dateCreated` by default; see `--help` for each command's choices) |
| `--asc` | Sort ascending (newest first by default) |
| `--expiring=` | Only licenses that expire within this many days, including ones already expired |
| `--page=` / `--per-page=` | Pagination (100 per page at most) |
| `--all` | Fetch every page |
| `--json` | Output the raw API response |

`--expiring` fetches every page and filters on your machine, because the API can't filter by expiry date.

### Plugin developer licenses

These commands manage the licenses issued for plugins you sell in the Plugin Store. They aren't affected by the current organization.

```bash
craft-console developer:list --plugin=my-plugin
craft-console developer:show ABCDEFGHIJKLMNOPQRSTUVWX

craft-console developer:create --plugin=my-plugin --edition=pro --email=client@example.com
craft-console developer:create --plugin=my-plugin --edition=pro --org=acme --expirable --count=5

craft-console developer:update ABCDEFGHIJKLMNOPQRSTUVWX --edition=pro --notes="Upgraded to Pro"
craft-console developer:update ABCDEFGHIJKLMNOPQRSTUVWX --non-expirable
```

Every developer command has a shorter `dev:` alias, such as `dev:list` and `dev:create`. A license's owner can't be changed after it's created.

### Composer packages

```bash
craft-console package craftcms/cms
craft-console package craftcms/cms --constraint="^5.0"
```

This command doesn't need you to log in.

### JSON output

Every command that reads data accepts `--json` for scripts and other tools:

```bash
craft-console plugin:list --all --json | jq '.licenses[] | select(.autoRenew == false) | .key'
```

## Development

```bash
git clone https://github.com/reusserdesign/craft-console.git
cd craft-console
composer install
./craft-console list
./vendor/bin/pest
./vendor/bin/pint
```

From a clone, `./craft-console` runs the source directly. `composer build` (or `scripts/build 0.2.0`) builds `builds/craft-console`, the phar that Composer installs, from the committed code in a temporary copy without the test tools. You only need this to try a build locally.

To release, commit your changes, then build the phar, commit it and tag that commit:

```bash
scripts/build 0.2.0
git add builds/craft-console
git commit -m "Build v0.2.0"
git tag -a v0.2.0 -m "v0.2.0"
git push origin main v0.2.0
```

Packagist picks up the new tag from there. GitHub Actions runs the tests on PHP 8.4, 8.5 and 8.6 for every push and pull request.

Tests use a throwaway config file in `tests/.craft-console` and block real HTTP requests, so they never touch your saved credentials or the live API.

The structure follows [laravel/forge-cli](https://github.com/laravel/forge-cli). It's a [Laravel Zero](https://laravel-zero.com) app with prompts from [Laravel Prompts](https://laravel.com/docs/prompts).

- `app/Clients/Craftnet.php` is the API client.
- `app/Commands` holds one class per command.
- `app/Commands/Concerns` has the shared listing, lookup and formatting code.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

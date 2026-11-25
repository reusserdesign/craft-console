# Craft Console CLI

A command-line client for [Craft Console](https://console.craftcms.com). List and look up the Craft and plugin licenses you or your organizations own, find renewals coming due, and issue or update licenses for plugins you develop.

It talks to the [Craftnet API](https://docs.craftcms.com/craftnet/), the API behind Craft Console. The binary is called `craft-console` so it doesn't clash with a Craft project's own `./craft` script.

## Requirements

- PHP 8.3 or later
- Composer

## Installation

```bash
git clone <this repository> craft-console
cd craft-console
composer install
```

Run it from the project directory with `./craft-console`, or put it on your `PATH`:

```bash
ln -s "$(pwd)/craft-console" /usr/local/bin/craft-console
```

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
| `CRAFT_CONSOLE_USERNAME` | Username or email. Overrides the saved one. |
| `CRAFT_CONSOLE_API_KEY` | API key. Overrides the saved one. |
| `CRAFT_CONSOLE_CONFIG` | Path to the config file, instead of `~/.craft-console/config.json`. |

Setting the first two lets you use the CLI in CI without running `login`.

## Organizations

By default, license commands use your personal account. To work with an organization's licenses, switch to it by its slug (the one in its Craft Console URL):

```bash
craft-console org:switch acme
craft-console org:current
craft-console org:switch --personal
```

The Craftnet API can't list your organizations, so `org:switch` checks that you have access to the slug you give it. `org:list` shows the organizations you've switched to before.

Any license command also accepts `--org=<slug>` or `--personal` to override the current organization for a single run.

## Commands

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
composer install
./vendor/bin/pest
./vendor/bin/pint
```

Tests use a throwaway config file in `tests/.craft-console` and block real HTTP requests, so they never touch your saved credentials or the live API.

The structure follows [laravel/forge-cli](https://github.com/laravel/forge-cli). It's a [Laravel Zero](https://laravel-zero.com) app with prompts from [Laravel Prompts](https://laravel.com/docs/prompts).

- `app/Clients/Craftnet.php` is the API client.
- `app/Commands` holds one class per command.
- `app/Commands/Concerns` has the shared listing, lookup and formatting code.

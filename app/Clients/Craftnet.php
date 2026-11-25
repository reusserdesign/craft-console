<?php

namespace App\Clients;

use App\Exceptions\CraftnetException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A thin client for the Craftnet API, the API behind Craft Console.
 *
 * @see https://docs.craftcms.com/craftnet/
 */
class Craftnet
{
    public const BASE_URL = 'https://api.craftcms.com/v1/';

    public const MAX_PER_PAGE = 100;

    /**
     * Resolves credentials when none have been set explicitly.
     *
     * @var (Closure(): array{0: ?string, 1: ?string})|null
     */
    protected ?Closure $credentialsResolver = null;

    public function __construct(
        protected ?string $username = null,
        protected ?string $apiKey = null,
    ) {}

    /**
     * Set the credentials used to authenticate requests.
     */
    public function setCredentials(?string $username, ?string $apiKey): static
    {
        $this->username = $username;
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Resolve credentials lazily, at the time of the first request.
     *
     * @param  Closure(): array{0: ?string, 1: ?string}  $resolver
     */
    public function resolveCredentialsUsing(Closure $resolver): static
    {
        $this->credentialsResolver = $resolver;

        return $this;
    }

    /**
     * Determine if credentials are available.
     */
    public function hasCredentials(): bool
    {
        if ((blank($this->username) || blank($this->apiKey)) && $this->credentialsResolver) {
            [$this->username, $this->apiKey] = ($this->credentialsResolver)();
        }

        return filled($this->username) && filled($this->apiKey);
    }

    /**
     * List Craft licenses owned by you, or by the given organization.
     *
     * @param  array<string, mixed>  $query
     * @return array{total: int, totalPages: int, licenses: array<int, array<string, mixed>>}
     */
    public function cmsLicenses(?string $org = null, array $query = []): array
    {
        return $this->get($this->scoped($org, 'licenses/cms'), $query);
    }

    /**
     * Get a Craft license by its ID.
     *
     * @return array<string, mixed>
     */
    public function cmsLicense(?string $org, int|string $id): array
    {
        return $this->get($this->scoped($org, "licenses/cms/{$id}"))['license'];
    }

    /**
     * List plugin licenses owned by you, or by the given organization.
     *
     * @param  array<string, mixed>  $query
     * @return array{total: int, totalPages: int, licenses: array<int, array<string, mixed>>}
     */
    public function pluginLicenses(?string $org = null, array $query = []): array
    {
        return $this->get($this->scoped($org, 'licenses/plugins'), $query);
    }

    /**
     * Get a plugin license by its ID.
     *
     * @return array<string, mixed>
     */
    public function pluginLicense(?string $org, int|string $id): array
    {
        return $this->get($this->scoped($org, "licenses/plugins/{$id}"))['license'];
    }

    /**
     * List licenses issued for plugins you develop.
     *
     * @param  array<string, mixed>  $query
     * @return array{total: int, totalPages: int, licenses: array<int, array<string, mixed>>}
     */
    public function developerLicenses(array $query = []): array
    {
        return $this->get('plugin-licenses', $query);
    }

    /**
     * Get a license issued for one of your plugins, by key or ID.
     *
     * @return array<string, mixed>
     */
    public function developerLicense(int|string $keyOrId): array
    {
        return $this->get('plugin-licenses/'.rawurlencode((string) $keyOrId))['license'];
    }

    /**
     * Create a license for one of your plugins.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function createDeveloperLicense(array $attributes): array
    {
        return $this->post('plugin-licenses', $attributes)['license'];
    }

    /**
     * Update a license for one of your plugins.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateDeveloperLicense(int|string $keyOrId, array $attributes): array
    {
        return $this->post('plugin-licenses/'.rawurlencode((string) $keyOrId), $attributes)['license'];
    }

    /**
     * Get information about a Composer package.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function package(string $name, array $query = []): array
    {
        return $this->get('package/'.$name, $query, authenticate: false);
    }

    /**
     * Prefix the given path with the organization scope, if any.
     */
    protected function scoped(?string $org, string $path): string
    {
        return filled($org) ? 'org/'.rawurlencode($org).'/'.$path : $path;
    }

    /**
     * Make a GET request.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = [], bool $authenticate = true): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get($path, array_filter(
            $query,
            fn ($value) => ! is_null($value) && $value !== '',
        )), $authenticate);
    }

    /**
     * Make a POST request with a JSON body.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function post(string $path, array $data): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post($path, $data));
    }

    /**
     * Send a request and return the decoded JSON body, aborting with a
     * readable message when the API responds with an error.
     *
     * @param  callable(PendingRequest): Response  $callback
     * @return array<string, mixed>
     */
    protected function send(callable $callback, bool $authenticate = true): array
    {
        $request = Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->withUserAgent('craft-console-cli/'.config('app.version'));

        if ($authenticate) {
            abort_unless(
                $this->hasCredentials(),
                1,
                "You are not logged in. Run 'craft-console login' first.",
            );

            $request->withBasicAuth((string) $this->username, (string) $this->apiKey);
        }

        try {
            $response = $callback($request);
        } catch (ConnectionException $e) {
            throw new CraftnetException('Could not reach the Craftnet API: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new CraftnetException($this->errorMessage($response), $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Build a readable message for a failed response.
     */
    protected function errorMessage(Response $response): string
    {
        $detail = $response->json('message')
            ?? $response->json('error')
            ?? collect($response->json('errors') ?? [])->flatten()->implode(' ');

        // Craftnet answers bad credentials with a 400 rather than a 401.
        if ($response->status() === 401 || (is_string($detail) && stripos($detail, 'invalid credentials') !== false)) {
            return "Craft Console rejected the username or API key. Run 'craft-console login' again.";
        }

        $message = match ($response->status()) {
            403 => 'You do not have access to that resource.',
            404 => 'Not found. It may not exist, or it is not owned by you or the selected organization.',
            429 => 'Too many requests. Wait a moment and try again.',
            default => "The Craftnet API responded with HTTP {$response->status()}.",
        };

        return filled($detail) && is_string($detail)
            ? "{$message} {$detail}"
            : $message;
    }
}

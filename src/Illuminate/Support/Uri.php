<?php

namespace Illuminate\Support;

use Closure;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use League\Uri\Contracts\UriInterface;
use League\Uri\Uri as LeagueUri;
use SensitiveParameter;
use Stringable;

class Uri implements Htmlable
{
    use Conditionable, Tappable;

    /**
     * The URL generator resolver.
     */
    protected static ?Closure $urlGeneratorResolver = null;

    /**
     * Create a new parsed URI instance.
     */
    public function __construct(UriInterface|Stringable|string $uri = '')
    {
        $this->uri = $uri instanceof UriInterface ? $uri : LeagueUri::new((string) $uri);
    }

    /**
     * Create a new URI instance.
     */
    public static function of(UriInterface|Stringable|string $uri = ''): static
    {
        return new static($uri);
    }

    /**
     * Get a URI instance of an absolute URL for the given path.
     */
    public static function to(string $path): static
    {
        return new static(call_user_func(static::$urlGeneratorResolver)->to($path));
    }

    /**
     * Get a URI instance for a named route.
     *
     * @param  \BackedEnum|string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return static
     *
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException|\InvalidArgumentException
     */
    public static function route($name, $parameters = [], $absolute = true): static
    {
        return new static(call_user_func(static::$urlGeneratorResolver)->route($name, $parameters, $absolute));
    }

    /**
     * Get the URI's scheme.
     */
    public function scheme(): ?string
    {
        return $this->uri->getScheme();
    }

    /**
     * Get the user from the URI.
     */
    public function user(bool $withPassword = false): ?string
    {
        if ($withPassword) {
            return $this->uri->getUserInfo();
        }

        $userInfo = $this->uri->getUserInfo();

        if (is_null($userInfo)) {
            return null;
        }

        return str_contains($userInfo, ':')
            ? Str::before($userInfo, ':')
            : $userInfo;
    }

    /**
     * Get the password from the URI.
     */
    public function password(): ?string
    {
        $userInfo = $this->uri->getUserInfo();

        return ! is_null($userInfo) ? Str::after($userInfo, ':') : null;
    }

    /**
     * Get the URI's host.
     */
    public function host(): ?string
    {
        return $this->uri->getHost();
    }

    /**
     * Get the URI's port.
     */
    public function port(): ?int
    {
        return $this->uri->getPort();
    }

    /**
     * Get the URI's path.
     *
     * Empty or missing paths are returned as a single "/".
     */
    public function path(): ?string
    {
        $path = trim((string) $this->uri->getPath(), '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Get the URI's query string.
     */
    public function query(): UriQueryString
    {
        return new UriQueryString($this);
    }

    /**
     * Get the URI's fragment.
     */
    public function fragment(): ?string
    {
        return $this->uri->getFragment();
    }

    /**
     * Specify the scheme of the URI.
     */
    public function withScheme(Stringable|string $scheme): static
    {
        return new static($this->uri->withScheme($scheme));
    }

    /**
     * Specify the user and password for the URI.
     */
    public function withUser(Stringable|string|null $user, #[SensitiveParameter] Stringable|string|null $password = null): static
    {
        return new static($this->uri->withUserInfo($user, $password));
    }

    /**
     * Specify the host of the URI.
     */
    public function withHost(Stringable|string $host): static
    {
        return new static($this->uri->withHost($host));
    }

    /**
     * Specify the port of the URI.
     */
    public function withPort(int|null $port): static
    {
        return new static($this->uri->withPort($port));
    }

    /**
     * Specify the path of the URI.
     */
    public function withPath(Stringable|string $path): static
    {
        return new static($this->uri->withPath(Str::start((string) $path, '/')));
    }

    /**
     * Merge new query parameters into the URI.
     */
    public function withQuery(array $query, bool $merge = true): static
    {
        foreach ($query as $key => $value) {
            if ($value instanceof UrlRoutable) {
                $query[$key] = $value->getRouteKey();
            }
        }

        if ($merge) {
            $mergedQuery = $this->query()->all();

            foreach ($query as $key => $value) {
                data_set($mergedQuery, $key, $value);
            }

            $newQuery = $mergedQuery;
        } else {
            $newQuery = $query;
        }

        return new static($this->uri->withQuery(Arr::query($newQuery)));
    }

    /**
     * Merge new query parameters into the URI if they are not already in the query string.
     */
    public function withQueryIfMissing(array $query): static
    {
        $currentQuery = $this->query();

        foreach ($query as $key => $value) {
            if (! $currentQuery->missing($key)) {
                Arr::forget($query, $key);
            }
        }

        return $this->withQuery($query);
    }

    /**
     * Push a value onto the end of a query string parameter that is a list.
     */
    public function pushOntoQuery(string $key, mixed $value): static
    {
        $currentValue = data_get($this->query()->all(), $key);

        return $this->withQuery([$key => match (true) {
            is_array($currentValue) && array_is_list($currentValue) => array_values(array_unique([...$currentValue, $value])),
            is_array($currentValue) => [...$currentValue, $value],
            ! is_null($currentValue) => [$currentValue, $value],
            default => Arr::wrap($value),
        }]);
    }

    /**
     * Remove the given query parameters from the URI.
     */
    public function withoutQuery(array $keys): static
    {
        return $this->replaceQuery(Arr::except($this->query()->all(), $keys));
    }

    /**
     * Specify new query parameters for the URI.
     */
    public function replaceQuery(array $query): static
    {
        return $this->withQuery($query, merge: false);
    }

    /**
     * Specify the fragment of the URI.
     */
    public function withFragment(string $fragment): static
    {
        return new static($this->uri->withFragment($fragment));
    }

    /**
     * Set the URL generator resolver.
     */
    public static function setUrlGeneratorResolver(Closure $urlGeneratorResolver): void
    {
        static::$urlGeneratorResolver = $urlGeneratorResolver;
    }

    /**
     * Get the underlying URI instance.
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Get content as a string of HTML.
     *
     * @return string
     */
    public function toHtml()
    {
        return (string) $this;
    }

    /**
     * Get the string representation of the URI.
     */
    public function __toString(): string
    {
        return $this->uri->toString();
    }
}

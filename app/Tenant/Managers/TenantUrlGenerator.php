<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantUrlGeneratorInterface;
use Illuminate\Contracts\Routing\UrlGenerator;
use RuntimeException;

final class TenantUrlGenerator implements TenantUrlGeneratorInterface
{
    public function __construct(
        private readonly UrlGenerator $urlGenerator,
        private readonly TenantContextInterface $context
    ) {}

    public function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $url = $this->urlGenerator->route($name, $parameters, $absolute);

        return $this->replaceBaseUrl($url);
    }

    public function url(string $path): string
    {
        $url = $this->urlGenerator->to($path);

        return $this->replaceBaseUrl($url);
    }

    public function getBaseUrl(): string
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            throw new RuntimeException('Cannot generate tenant base URL without an active tenant context.');
        }

        $defaultUrl = $this->urlGenerator->to('/');
        $parsedScheme = parse_url($defaultUrl, PHP_URL_SCHEME);
        $scheme = is_string($parsedScheme) && $parsedScheme !== '' ? $parsedScheme : 'http';
        $domain = $tenant->uuid.'.localhost';

        return "{$scheme}://{$domain}";
    }

    private function replaceBaseUrl(string $url): string
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return $url;
        }

        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['host'])) {
            return $url;
        }

        $scheme = $parsed['scheme'] ?? 'http';

        $portRaw = $parsed['port'] ?? null;
        $port = is_numeric($portRaw) ? ':'.((int) $portRaw) : '';

        $path = $parsed['path'] ?? '';

        $queryRaw = $parsed['query'] ?? null;
        $query = is_string($queryRaw) && $queryRaw !== '' ? '?'.$queryRaw : '';

        $tenantDomain = $tenant->uuid.'.localhost';

        return "{$scheme}://{$tenantDomain}{$port}{$path}{$query}";
    }
}

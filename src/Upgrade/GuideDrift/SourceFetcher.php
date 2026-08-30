<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

interface SourceFetcher
{
    /**
     * @throws \RuntimeException when a source cannot be fetched or is invalid.
     */
    public function fetch(string $url, int $maxBytes): string;
}

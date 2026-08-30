<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Fetches the small, fixed set of upstream sources used by the drift check.
 *
 * cURL is preferred because it exposes connect and total timeouts and lets us
 * enforce a byte limit while the response is being received. The stream
 * fallback is kept for minimal PHP installations and applies the same body
 * limit after each read.
 */
final class HttpSourceFetcher implements SourceFetcher
{
    private const CONNECT_TIMEOUT = 5;

    private const TIMEOUT = 20;

    /**
     * @param  Closure(string, int): array{status: int, body: string, headers?: list<string>}|null  $transport
     */
    public function __construct(private readonly ?Closure $transport = null) {}

    public function fetch(string $url, int $maxBytes): string
    {
        if ($maxBytes < 1) {
            throw new RuntimeException('A positive source size limit is required.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException(sprintf('Refusing non-HTTPS source URL "%s".', $url));
        }

        if ($this->transport !== null) {
            try {
                $response = ($this->transport)($url, $maxBytes);
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $exception->getMessage()), 0, $exception);
            }

            return $this->validateResponse(
                $url,
                $maxBytes,
                $response['status'],
                $response['body'],
                $response['headers'] ?? [],
            );
        }

        if (function_exists('curl_init')) {
            return $this->fetchWithCurl($url, $maxBytes);
        }

        return $this->fetchWithStreams($url, $maxBytes);
    }

    private function fetchWithCurl(string $url, int $maxBytes): string
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not initialise an HTTP client for "%s".', $url));
        }

        $body = '';
        $tooLarge = false;
        $headers = [];

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'laravel-upgrades-rector-guide-drift/1.x',
            CURLOPT_HTTPHEADER => ['Accept: text/markdown, text/html, application/json'],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                unset($handle);

                if (trim($line) !== '') {
                    $headers[] = rtrim($line, "\r\n");
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                unset($handle);

                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        $result = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($tooLarge) {
            throw new RuntimeException(sprintf('Source "%s" exceeds the %d-byte limit.', $url, $maxBytes));
        }

        if ($result === false) {
            throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $error !== '' ? $error : 'unknown HTTP error'));
        }

        return $this->validateResponse($url, $maxBytes, $status, $body, $headers);
    }

    private function fetchWithStreams(string $url, int $maxBytes): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => "Accept: text/markdown, text/html, application/json\r\nUser-Agent: laravel-upgrades-rector-guide-drift/1.x\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $stream = fopen($url, 'rb', false, $context);
        } finally {
            restore_error_handler();
        }

        if ($stream === false) {
            throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $warning ?? 'unknown HTTP error'));
        }

        $defined = get_defined_vars();
        $headers = is_array($defined['http_response_header'] ?? null) ? $defined['http_response_header'] : [];
        $status = $this->statusCode($headers);
        $contentLength = $this->headerValue($headers, 'content-length');

        if ($contentLength !== null && ctype_digit($contentLength) && (int) $contentLength > $maxBytes) {
            fclose($stream);

            throw new RuntimeException(sprintf('Source "%s" exceeds the %d-byte limit.', $url, $maxBytes));
        }

        $body = '';

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);

                if ($chunk === false) {
                    throw new RuntimeException(sprintf('Could not read source "%s".', $url));
                }

                $body .= $chunk;

                if (strlen($body) > $maxBytes) {
                    throw new RuntimeException(sprintf('Source "%s" exceeds the %d-byte limit.', $url, $maxBytes));
                }
            }
        } finally {
            fclose($stream);
        }

        return $this->validateResponse($url, $maxBytes, $status, $body, $headers);
    }

    /**
     * @param  list<string>  $headers
     */
    private function validateResponse(string $url, int $maxBytes, int $status, string $body, array $headers): string
    {
        if (strlen($body) > $maxBytes) {
            throw new RuntimeException(sprintf('Source "%s" exceeds the %d-byte limit.', $url, $maxBytes));
        }

        if ($status < 200 || $status >= 300) {
            $message = sprintf('Source "%s" returned HTTP status %d.', $url, $status);
            $remaining = $this->headerValue($headers, 'x-ratelimit-remaining');

            if (($status === 403 || $status === 429) && $remaining === '0') {
                $retryAfter = $this->headerValue($headers, 'retry-after');
                $message .= ' API rate limit exhausted.';

                if ($retryAfter !== null && $retryAfter !== '') {
                    $message .= sprintf(' Retry after %s.', $retryAfter);
                }
            }

            throw new RuntimeException($message);
        }

        if ($body === '') {
            throw new RuntimeException(sprintf('Source "%s" returned an empty response.', $url));
        }

        return $body;
    }

    /**
     * @param  list<string>  $headers
     */
    private function statusCode(array $headers): int
    {
        $status = 0;

        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }

    /**
     * @param  list<string>  $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), strtolower($name).':')) {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }
}

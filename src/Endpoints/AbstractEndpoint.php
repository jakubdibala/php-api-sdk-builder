<?php

declare(strict_types=1);

namespace WrkFlow\ApiSdkBuilder\Endpoints;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Throwable;
use WrkFlow\ApiSdkBuilder\Contracts\ApiFactoryContract;
use WrkFlow\ApiSdkBuilder\Entities\EndpointDIEntity;
use WrkFlow\ApiSdkBuilder\Interfaces\ApiInterface;
use WrkFlow\ApiSdkBuilder\Interfaces\EndpointInterface;
use WrkFlow\ApiSdkBuilder\Interfaces\HeadersInterface;
use WrkFlow\ApiSdkBuilder\Interfaces\OptionsInterface;
use WrkFlow\ApiSdkBuilder\Log\Contracts\FileLoggerContract;
use WrkFlow\ApiSdkBuilder\Responses\AbstractResponse;

/**
 * Endpoint names that contains all API endpoint methods that sends a request.
 * This class should be immutable because it is cached.
 *
 * @phpstan-import-type IgnoreLoggersOnExceptionClosure from ApiInterface
 */
abstract class AbstractEndpoint implements EndpointInterface
{
    /**
     * @var IgnoreLoggersOnExceptionClosure|null
     */
    private Closure|null $shouldIgnoreLoggersOnException = null;

    public function __construct(
        protected readonly EndpointDIEntity $di,
    ) {
    }

    final public function setShouldIgnoreLoggersForExceptions(Closure $closure): static
    {
        $cloned = clone $this;

        $cloned->shouldIgnoreLoggersOnException = $closure;

        return $cloned;
    }

    final public function dontReportExceptionsToFile(array $exceptions): static
    {
        return $this->setShouldIgnoreLoggersForExceptions(static function (Throwable $throwable) use (
            $exceptions
        ): array {
            foreach ($exceptions as $exception) {
                if ($throwable instanceof $exception) {
                    return [FileLoggerContract::class];
                }
            }
            return [];
        });
    }

    final protected function shouldIgnoreLoggersOnException(): ?Closure
    {
        return function (Throwable $throwable): array {
            $return = [];
            $globalClosure = $this->di->api()
                ->shouldIgnoreLoggersOnException();

            if ($globalClosure !== null) {
                $return += $globalClosure($throwable);
            }

            $localClosure = $this->shouldIgnoreLoggersOnException;
            if ($localClosure !== null) {
                $return += $localClosure($throwable);
            }

            return $return;
        };
    }


    /**
     * Appends to base path in uri. Must start with /.
     */
    abstract protected function basePath(): string;

    final protected function uri(string $appendPath = ''): UriInterface
    {
        $uri = $this->di->api()
            ->uri();
        $basePath = $this->appendSlashIfNeeded($this->basePath());
        $appendPath = $this->appendSlashIfNeeded($appendPath);

        return $uri->withPath($uri->getPath() . $basePath . $appendPath);
    }


    /**
     * @template TResponse of AbstractResponse
     *
     * @param class-string<TResponse>                            $responseClass
     * @param array<int|string,HeadersInterface|string|string[]> $headers
     *
     * @return TResponse
     */
    final protected function sendGet(
        string $responseClass,
        UriInterface $uri,
        array $headers = [],
        ?int $expectedResponseStatusCode = null,
    ): AbstractResponse {
        $request = $this->factory()
            ->request()
            ->createRequest('GET', (string) $uri);

        return $this
            ->di
            ->sendRequestAction()
            ->execute(
                api: $this
                    ->di
                    ->api(),
                request: $request,
                responseClass: $responseClass,
                headers: $headers,
                expectedResponseStatusCode: $expectedResponseStatusCode,
                shouldIgnoreLoggersOnError: $this->shouldIgnoreLoggersOnException(),
            );
    }

    /**
     * @template TResponse of AbstractResponse
     *
     * @param class-string<TResponse>                            $responseClass
     * @param array<int|string,HeadersInterface|string|string[]> $headers
     * @param int|null                                           $expectedResponseStatusCode Will raise and failed
     *                                                                                      exception if response
     *
     * @return TResponse
     */
    final protected function sendPost(
        string $responseClass,
        UriInterface $uri,
        OptionsInterface|StreamInterface|string|null $body = null,
        array $headers = [],
        ?int $expectedResponseStatusCode = null,
    ): AbstractResponse {
        $request = $this->factory()
            ->request()
            ->createRequest('POST', (string) $uri);

        return $this->di->sendRequestAction()
            ->execute(
                api: $this
                    ->di
                    ->api(),
                request: $request,
                responseClass: $responseClass,
                body: $body,
                headers: $headers,
                expectedResponseStatusCode: $expectedResponseStatusCode,
                shouldIgnoreLoggersOnError: $this->shouldIgnoreLoggersOnException(),
            );
    }

    /**
     * @template TResponse of AbstractResponse
     *
     * @param class-string<TResponse>                            $responseClass
     * @param array<int|string,HeadersInterface|string|string[]> $headers
     * @param int|null                                           $expectedResponseStatusCode Will raise and failed
     *                                                                                      exception if response
     *
     * @return TResponse
     */
    final protected function sendPut(
        string $responseClass,
        UriInterface $uri,
        OptionsInterface|StreamInterface|string|null $body = null,
        array $headers = [],
        ?int $expectedResponseStatusCode = null,
        ?Closure $shouldIgnoreLoggersOnError = null,
    ): AbstractResponse {
        $request = $this->factory()
            ->request()
            ->createRequest('PUT', (string) $uri);

        return $this
            ->di
            ->sendRequestAction()
            ->execute(
                api: $this
                    ->di
                    ->api(),
                request: $request,
                responseClass: $responseClass,
                body: $body,
                headers: $headers,
                expectedResponseStatusCode: $expectedResponseStatusCode,
                shouldIgnoreLoggersOnError: $this->shouldIgnoreLoggersOnException(),
            );
    }

    /**
     * @template TResponse of AbstractResponse
     *
     * @param class-string<TResponse>                            $responseClass
     * @param array<int|string,HeadersInterface|string|string[]> $headers
     * @param int|null                                           $expectedResponseStatusCode Will raise and failed
     *                                                                                      exception if response
     *
     * @return TResponse
     */
    final protected function sendDelete(
        string $responseClass,
        UriInterface $uri,
        OptionsInterface|StreamInterface|string|null $body = null,
        array $headers = [],
        ?int $expectedResponseStatusCode = null,
    ): AbstractResponse {
        $request = $this->factory()
            ->request()
            ->createRequest('DELETE', (string) $uri);

        return $this
            ->di
            ->sendRequestAction()
            ->execute(
                api: $this
                    ->di
                    ->api(),
                request: $request,
                responseClass: $responseClass,
                body: $body,
                headers: $headers,
                expectedResponseStatusCode: $expectedResponseStatusCode,
                shouldIgnoreLoggersOnError: $this->shouldIgnoreLoggersOnException(),
            );
    }

    /**
     * Sends a fake request with fake response (will all events in place).
     *
     * @template TResponse of AbstractResponse
     *
     * @param class-string<TResponse>                            $responseClass
     * @param array<int|string,HeadersInterface|string|string[]> $headers
     * @param int|null                                           $expectedResponseStatusCode Will raise and failed
     * exception if response
     *
     * @return TResponse
     */
    final protected function sendFake(
        ResponseInterface $response,
        string $responseClass,
        UriInterface $uri,
        OptionsInterface|StreamInterface|string|null $body = null,
        array $headers = [],
        ?int $expectedResponseStatusCode = null,
    ): AbstractResponse {
        return $this
            ->di
            ->sendRequestAction()
            ->execute(
                api: $this
                    ->di
                    ->api(),
                request: $this
                    ->factory()
                    ->request()
                    ->createRequest('FAKE', (string) $uri),
                responseClass: $responseClass,
                body: $body,
                headers: $headers,
                expectedResponseStatusCode: $expectedResponseStatusCode,
                fakedResponse: $response,
                shouldIgnoreLoggersOnError: $this->shouldIgnoreLoggersOnException(),
            );
    }

    final protected function factory(): ApiFactoryContract
    {
        return $this
            ->di
            ->api()
            ->factory();
    }

    private function appendSlashIfNeeded(string $path): string
    {
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }
}

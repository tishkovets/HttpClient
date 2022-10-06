<?php

namespace HttpClient;

use Choval\Async;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;

class HttpClient
{
    public Client $guzzle;
    protected array $config;

    /**
     * HttpClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('cookies', $config) and is_array($config['cookies'])) {
            $config['cookies'] = new CookieJar(false, $config['cookies']);
        } elseif (!(array_key_exists('cookies', $config) and $config['cookies'] instanceof CookieJar)) {
            $config['cookies'] = new CookieJar(false, []);
        }

        $this->guzzle = new Client(['cookies' => $config['cookies']]);
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getConfigOption($option)
    {
        return @$this->config[$option];
    }

    /**
     * @param array $config
     *
     * @return $this
     */
    public function setConfig(array $config): HttpClient
    {
        if (array_key_exists('cookies', $config) and is_array($config['cookies'])) {
            $config['cookies'] = new CookieJar(false, $config['cookies']);
        }

        $this->config = $config;

        return $this;
    }

    public function setConfigKey($key, $value): HttpClient
    {
        $this->config[$key] = $value;

        return $this;
    }

    public function request($uri, array $specified = []): Request
    {
        return new Request($this, $uri, $specified);
    }

    /**
     * @param Request       $request
     * @param Response|null $response
     *
     * @return Response
     * @throws GuzzleException
     */
    public function send(Request $request, Response $response = null): Response
    {
        if (is_null($response)) {
            $response = $request->getWrapper();
        }

        $r = $this->guzzle->requestAsync($request->getMethod(), $request->getUri(), $request->getConfig())->then(
            function (ResponseInterface $r) use ($request, $response) {
                return $response($r, $request->getBaseUri());
            },
            function ($r) use ($request, $response) {
                $currentAttempt = $request->getOption('_currentAttempt') ?? 0;
                if (++$currentAttempt < $request->connectAttempts()) {
                    $request->setOption('_currentAttempt', $currentAttempt);
                    $sleep = $request->connectSleep();
                    if ($sleep > 0) {
                        #TODO async sleep
                    }

                    return $this->send($request, $response);
                } else {
                    throw $r;
                }
            }
        );

        return $r->wait();
    }

    /**
     * batch request handling
     *
     * @param array         $requests
     * @param int           $concurrency
     * @param callable|null $onFulfill
     * this is delivered each successful response
     * function ($response, $index) {}
     *
     * @param callable|null $onReject
     * this is delivered each failed request
     * function ($reason, $index) {}
     *
     * @return array
     */
    public function batch(
        array $requests,
        int $concurrency = 1,
        callable $onFulfill = null,
        callable $onReject = null
    ): array {
        $generator = function ($requests) {
            foreach ($requests as $request) {
                yield function () use ($request) {
                    return $this->send($request);
                };
            }
        };

        $options = ['concurrency' => $concurrency];
        if (isset($onFulfill)) {
            $options['fulfilled'] = $onFulfill;
        }
        if (isset($onReject)) {
            $options['rejected'] = $onReject;
        }

        return Pool::batch($this->guzzle, $generator($requests), $options);
    }

    public function getCookies(): array
    {
        $cookies = [];
        if ($this->getConfigOption('cookies') instanceof CookieJar) {
            $cookieArray = array_map(function ($array) {
                return array_diff($array, [false, null]);
            }, $this->getConfigOption('cookies')->toArray());

            foreach ($cookieArray as $cookie) { # delete outdated cookies
                if (!($cookie instanceof SetCookie)) {
                    $check = new SetCookie($cookie);
                    if (!$check->isExpired()) {
                        $cookies[] = $cookie;
                    }
                }
            }
        }

        return $cookies;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->guzzle;
    }
}
<?php

namespace HttpClient;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;

class Request
{
    protected HttpClient $httpClient;
    protected string $uri;
    protected array $config = [];
    protected string $method;

    const DEFAULT_CONNECT_ATTEMPTS = 1;

    /**
     * sleep interval between connect attempts
     */
    const DEFAULT_CONNECT_SLEEP = 0;

    public function __construct(HttpClient $httpClient, $uri, array $specifiedConfig = [])
    {
        $this->httpClient = &$httpClient;
        $this->uri = $uri;
        $this->config = $httpClient->getConfig();

        foreach ($specifiedConfig as $item) {
            if (is_array($item) and method_exists($this, $item[0])) {
                $method = array_shift($item);
                $this->$method(...$item);
            }
        }
    }

    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function setUri($uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getBaseUri()
    {
        if (isset($this->config['base_uri'])) {
            return $this->config['base_uri'];
        }

        $p = parse_url($this->uri);

        return (isset($p['host']) and isset($p['scheme'])) ? ($p['scheme'] . '://' . $p['host']) : null;
    }

    public function getMethod(): string
    {
        if (isset($this->method)) {
            return $this->method;
        } elseif (isset($this->config['form_params']) and is_array($this->config['form_params']) and sizeof($this->config['form_params']) > 0) {
            return 'POST';
        } elseif (isset($this->config['multipart']) and is_array($this->config['multipart']) and sizeof($this->config['multipart']) > 0) {
            return 'POST';
        } elseif (isset($this->config['json']) and is_array($this->config['json']) and sizeof($this->config['json']) > 0) {
            return 'POST';
        }

        return 'GET';
    }

    public function setMethod($method): self
    {
        $this->method = (string)$method;

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getOption($name)
    {
        return $this->config[$name] ?? null;
    }

    public function addOption($key, $value): Request
    {
        if (is_array($value) and isset($this->config[$key]) and is_array($this->config[$key])) {
            $this->config[$key] = $value + $this->config[$key];
        } else {
            $this->config[$key] = $value;
        }

        return $this;
    }

    public function setOption($key, $value): Request
    {
        $this->config[$key] = $value;

        return $this;
    }

    public function removeOption($key): Request
    {
        unset($this->config[$key]);

        return $this;
    }

    public function addHeader($key, $value): Request
    {
        $this->config['headers'][$key] = $value;

        return $this;
    }

    public function addHeaders($options): Request
    {
        foreach ($options as $key => $value) {
            $this->addHeader($key, $value);
        }

        return $this;
    }

    public function removeHeader($key): Request
    {
        unset($this->config['headers'][$key]);

        return $this;
    }

    public function removeHeaders(): Request
    {
        $this->config['headers'] = [];

        return $this;
    }

    public function addQuery($key, $value): Request
    {
        $this->config['query'][$key] = $value;

        return $this;
    }

    public function addQueryBulk($options): Request
    {
        foreach ($options as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    public function query(): array
    {
        if (array_key_exists('query', $this->config)) {
            return $this->config['query'];
        }

        return [];
    }

    public function fetchQuery($key)
    {
        return $this->config['query'][$key] ?? null;
    }

    public function removeQuery($key): Request
    {
        unset($this->config['query'][$key]);

        return $this;
    }

    public function addPost($key, $value): Request
    {
        $this->config['form_params'][$key] = $value;

        return $this;
    }

    public function addPostBulk($options): Request
    {
        foreach ($options as $key => $value) {
            $this->addPost($key, $value);
        }

        return $this;
    }

    public function post(): array
    {
        if (array_key_exists('form_params', $this->config)) {
            return $this->config['form_params'];
        }

        return [];
    }

    public function fetchPost($key)
    {
        return $this->config['form_params'][$key] ?? null;
    }

    public function removePost($key): Request
    {
        unset($this->config['form_params'][$key]);

        return $this;
    }

    public function addJson($key, $value): Request
    {
        $this->config['json'][$key] = $value;

        return $this;
    }

    public function addJsonBulk($options): Request
    {
        foreach ($options as $key => $value) {
            $this->addJson($key, $value);
        }

        return $this;
    }

    public function json(): array
    {
        if (array_key_exists('json', $this->config)) {
            return $this->config['json'];
        }

        return [];
    }

    public function fetchJson($key)
    {
        return $this->config['json'][$key] ?? null;
    }


    public function removeJson($key): Request
    {
        unset($this->config['json'][$key]);

        return $this;
    }

    public function addMultipart($name, $contents, array $headers = [], $filename = null): Request
    {
        $this->config['multipart'][] = [
                'name'     => $name,
                'contents' => $contents,
            ]
            + (sizeof($headers) > 0 ? ['headers' => $headers] : [])
            + (!is_null($filename) ? ['filename' => $filename] : []);

        return $this;
    }

    public function setCookies($cookies): Request
    {
        if (is_array($cookies)) {
            $this->setOption('cookies', new CookieJar(false, $cookies));
        } elseif ($cookies instanceof CookieJar) {
            $this->setOption('cookies', $cookies);
        }

        return $this;
    }

    public function addCookies($cookies): Request
    {
        if (is_array($cookies)) {
            $this->setOption('cookies',
                new CookieJar(false, array_merge($this->getHttpClient()->getCookies(), $cookies)));
        } elseif ($cookies instanceof CookieJar) {
            $this->setOption('cookies',
                new CookieJar(false, array_merge($this->getHttpClient()->getCookies(), $cookies->toArray())));
            $this->setOption('cookies', $cookies);
        }

        return $this;
    }

    public function setWrapper($response): Request
    {
        $this->setOption('wrapper', $response);

        return $this;
    }

    public function getWrapper(): Response
    {
        if (isset($this->config['wrapper'])) {
            if ($this->config['wrapper'] instanceof Response) {
                return $this->config['wrapper'];
            } elseif (is_string($this->config['wrapper'])) {
                return new $this->config['wrapper'];
            }
        }

        return new Response();
    }

    public function connectAttempts(): int
    {
        if (isset($this->config['connectLimits']['attempts'])) {
            return (int)$this->config['connectLimits']['attempts']; #old way
        } elseif (isset($this->config['connectAttempts'])) {
            return (int)$this->config['connectAttempts'];
        }

        return self::DEFAULT_CONNECT_ATTEMPTS;
    }

    public function connectSleep(): int
    {
        if (isset($this->config['connectLimits']['sleep'])) {
            return (int)$this->config['connectLimits']['sleep']; #old way
        } elseif (isset($this->config['connectSleep'])) {
            return (int)$this->config['connectSleep'];
        }

        return self::DEFAULT_CONNECT_SLEEP;
    }


    /**
     * @param Response|null $responseClass
     *
     * @return Response
     * @throws GuzzleException
     */
    public function getResponse(Response $responseClass = null): Response
    {
        return $this->getHttpClient()->send($this, $responseClass);
    }

    public function handlerStack(): HandlerStack
    {
        return $this->getOption('handler');
    }
}
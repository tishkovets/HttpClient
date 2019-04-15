<?php

namespace HttpClient;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;

class Request
{
    protected $httpClient;
    protected $uri;
    protected $options = [];

    public function __construct(HttpClient $httpClient, $uri, array $specified = [])
    {
        $this->httpClient = &$httpClient;
        $this->uri = $uri;
        $this->options = $httpClient->getConfig();

        foreach ($specified as $item) {
            if (is_array($item) AND method_exists($this, $item[0])) {
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

    public function getMethod()
    {
        if (sizeof(@$this->options['form_params']) > 0 OR sizeof(@$this->options['json']) > 0 OR sizeof(@$this->options['multipart']) > 0) {
            return 'POST';
        }

        return 'GET';
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function addOption($key, $value)
    {
        if (is_array($value) AND is_array(@$this->options[$key])) {
            $this->options[$key] = $value + $this->options[$key];
        } else {
            $this->options[$key] = $value;
        }

        return $this;
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function removeOption($key)
    {
        unset($this->options[$key]);

        return $this;
    }

    public function addHeader($key, $value)
    {
        $this->options['headers'][$key] = $value;

        return $this;
    }

    public function addHeaders($options)
    {
        foreach ($options as $key => $value) {
            $this->addHeader($key, $value);
        }

        return $this;
    }

    public function removeHeader($key)
    {
        unset($this->options['headers'][$key]);

        return $this;
    }

    public function removeHeaders()
    {
        $this->options['headers'] = [];

        return $this;
    }

    public function addQuery($key, $value)
    {
        $this->options['query'][$key] = $value;

        return $this;
    }

    public function addQueryBulk($options)
    {
        foreach ($options as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    public function fetchQuery(): array
    {
        if (array_key_exists('query', $this->options)) {
            return $this->options['query'];
        }

        return [];
    }

    public function fetchQueryKey($key)
    {
        return @$this->options['query'][$key];
    }

    public function addPost($key, $value)
    {
        $this->options['form_params'][$key] = $value;

        return $this;
    }

    public function addPostBulk($options)
    {
        foreach ($options as $key => $value) {
            $this->addPost($key, $value);
        }

        return $this;
    }

    public function fetchPost(): array
    {
        if (array_key_exists('form_params', $this->options)) {
            return $this->options['form_params'];
        }

        return [];
    }

    public function fetchPostKey($key)
    {
        return @$this->options['form_params'][$key];
    }

    public function addJson($key, $value)
    {
        $this->options['json'][$key] = $value;

        return $this;
    }

    public function addJsonBulk($options)
    {
        foreach ($options as $key => $value) {
            $this->addJson($key, $value);
        }

        return $this;
    }

    public function fetchJson(): array
    {
        if (array_key_exists('json', $this->options)) {
            return $this->options['json'];
        }

        return [];
    }

    public function fetchJsonKey($key)
    {
        return @$this->options['json'][$key];
    }

    public function addMultipart($name, $contents, array $headers = [], $filename = null)
    {
        $this->options['multipart'][] = [
                'name'     => $name,
                'contents' => $contents,
            ]
            + (sizeof($headers) > 0 ? ['headers' => $headers] : [])
            + (!is_null($filename) ? ['filename' => $filename] : []);

        return $this;
    }

    public function setCookies($cookies)
    {
        if (is_array($cookies)) {
            $this->setOption('cookies', new CookieJar(false, $cookies));
        } elseif ($cookies instanceof CookieJar) {
            $this->setOption('cookies', $cookies);
        }

        return $this;
    }

    public function addCookies($cookies)
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
}
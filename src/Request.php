<?php

namespace HttpClient;

class Request
{
    protected $httpClient;
    protected $uri;
    protected $options = [];

    public function __construct(HttpClient $httpClient, $uri, array $options = [])
    {
        $this->httpClient = &$httpClient;
        $this->uri = $uri;
        $this->options = $options;
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

    public function fetchQuery()
    {
        if (array_key_exists('query', $this->options)) {
            return $this->options['query'];
        }

        return [];
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

    public function fetchPost()
    {
        if (array_key_exists('form_params', $this->options)) {
            return $this->options['form_params'];
        }

        return [];
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

    public function fetchJson()
    {
        if (array_key_exists('json', $this->options)) {
            return $this->options['json'];
        }

        return [];
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

    /**
     * @param Response|null $responseClass
     *
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getResponse(Response $responseClass = null): Response
    {
        return $this->getHttpClient()->send($this, $responseClass);
    }
}
<?php

namespace HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ConnectException;
use HttpClient\Exception\HttpClientException;

class HttpClient
{
    protected $config;
    protected $client;
    protected $isResponseModified = false;
    protected $proxyCallbackMetrics = [
        'changeAmount'  => 0,
        'connectAmount' => 0,
    ];
    protected $proxyCallbackLimits = [];
    protected $proxyData = null;

    const DefaultProxyCallbackLimits = [
        'changeLimit'   => 1,
        'connectLimit'  => 2,
        'sleepInterval' => 5,
    ];

    /**
     * HttpClient constructor.
     *
     * @param array $config
     *
     * @throws HttpClientException
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('proxyCallback', $config) AND !is_callable($config['proxyCallback'])) {
            throw new HttpClientException('Bad config.');
        }

        if (array_key_exists('proxyCallbackLimits', $config) AND !is_array($config['proxyCallbackLimits'])) {
            $this->proxyCallbackLimits = self::DefaultProxyCallbackLimits;
        } else {
            $this->proxyCallbackLimits = $config['proxyCallbackLimits'] + self::DefaultProxyCallbackLimits;
        }

        if (array_key_exists('cookies', $config) AND is_array($config['cookies'])) {
            $config['cookies'] = new CookieJar(false, $config['cookies']);
        }

        $this->client = new Client();
        $this->config = $config;
    }

    public function request($uri, array $options = []): Request
    {
        if (sizeof($options) === 0) {
            $options = $this->config;
        }

        return new Request($this, $uri, $options);
    }

    public function runProxyCallback(callable $callback)
    {
        if ($this->proxyCallbackMetrics['changeAmount'] < $this->proxyCallbackLimits['changeLimit']) {
            $this->proxyCallbackMetrics['changeAmount']++;

            return call_user_func($callback);
        }

        return null;
    }

    /**
     * @param Request       $request
     * @param Response|null $response
     *
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function send(Request $request, Response $response = null): Response
    {
        if (is_null($response)) {
            $response = new Response();
        }

        $options = $request->getOptions();
        if ($this->isResponseModified == false) {
            if (!array_key_exists('handler', $options)) {
                $options['handler'] = \GuzzleHttp\HandlerStack::create();
            }
            $options['handler']->unshift(Response::modifyResponse($response));
            $this->isResponseModified = true;
        }

        try {
            $response = $this->client->request($request->getMethod(), $request->getUri(), $options);
            $this->proxyCallbackMetrics = [
                'change'  => 0,
                'connect' => 0,
            ];

            return $response;
        } catch (ConnectException $e) {
            if (array_key_exists('proxyCallback', $options)) {
                if (is_null($this->proxyData)) {
                    $this->proxyData = $this->runProxyCallback($options['proxyCallback']);
                } elseif ($this->proxyCallbackMetrics['connectAmount'] >= $this->proxyCallbackLimits['connectLimit']) {
                    $this->proxyData = null;
                    sleep($this->proxyCallbackLimits['sleepInterval']);
                    $this->proxyData = $this->runProxyCallback($options['proxyCallback']);
                } else {
                    $this->proxyCallbackMetrics['connectAmount']++;
                    sleep($this->proxyCallbackLimits['sleepInterval']);
                }

                if (is_array($this->proxyData) AND array_key_exists('proxy', $this->proxyData)) {
                    if (array_key_exists('type', $this->proxyData) AND $this->proxyData['type'] == 'socks5') {
                        $proxy = 'socks5://' . $this->proxyData['proxy'];
                    } else {
                        $proxy = $this->proxyData['proxy'];
                    }

                    $request->addOption('proxy', $proxy);

                    return $this->send($request, $response);
                }
            }

            throw $e;
        }
    }

    public function getCookies()
    {
        $cookies = [];
        if ($this->client->getConfig('cookies') instanceof CookieJar) {
            $cookieArray = array_map(function ($array) {
                return array_diff($array, [false, null]);
            }, $this->client->getConfig('cookies')->toArray());

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
}
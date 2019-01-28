<?php

namespace HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;

class HttpClient
{
    protected $config;
    protected $client;
    protected $proxyMetrics = [
        'changeAmount'  => 0,
        'connectAmount' => 0,
    ];
    protected $proxyLimits = [];
    protected $proxy;

    const DefaultProxyLimits = [
        'changeLimit'   => 0,
        'connectLimit'  => 1,
        'sleepInterval' => 0,
    ];

    /**
     * HttpClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $guzzleConfig = [];
        if (array_key_exists('cookies', $config) AND is_array($config['cookies'])) {
            $guzzleConfig['cookies'] = new CookieJar(false, $config['cookies']);
            unset($config['cookies']);
        } elseif (array_key_exists('cookies', $config) AND $config['cookies'] instanceof CookieJar) {
            $guzzleConfig['cookies'] = $config['cookies'];
            unset($config['cookies']);
        } else {
            $guzzleConfig['cookies'] = new CookieJar(false, []);
        }

        $this->client = new Client($guzzleConfig);

        $this->config = $config;
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        if (array_key_exists('cookies', $config) AND is_array($config['cookies'])) {
            $config['cookies'] = new CookieJar(false, $config['cookies']);
        }

        $this->config = $config;
    }

    public function request($uri, array $options = []): Request
    {
        if (sizeof($options) === 0) {
            $options = $this->config;
        }

        return new Request($this, $uri, $options);
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

        # modify response
        $options = $request->getOptions();
        if (!array_key_exists('handler', $options)) {
            $options['handler'] = HandlerStack::create();
        }
        $options['handler']->unshift(Response::modifyResponse($response));

        if (array_key_exists('proxy', $options) AND $options['proxy'] instanceof ProxyInterface) {
            $this->proxy = $options['proxy'];
            $options['proxy'] = $options['proxy']->getProxy();
        }

        if (array_key_exists('proxyLimits', $options) AND !is_array($options['proxyLimits'])) {
            $this->proxyLimits = self::DefaultProxyLimits;
        } elseif (array_key_exists('proxyLimits', $options)) {
            $this->proxyLimits = $options['proxyLimits'] + self::DefaultProxyLimits;
        }


        try {
            $response = $this->client->request($request->getMethod(), $request->getUri(), $options);
            $this->proxyMetrics['connect'] = 0;

            return $response;
        } catch (ConnectException $e) {
            if (array_key_exists('proxy', $options)) {
                $this->proxyMetrics['connectAmount']++;
                $proxy = null;

                if (!$this->isProxyConnectExceed()) {
                    $proxy = $options['proxy'];
                    sleep($this->proxyLimits['sleepInterval']);
                } elseif ($this->proxy instanceof ProxyInterface AND !$this->isProxyChangeExceed()) {
                    $proxy = $this->proxy->assignProxy();
                    $this->proxyMetrics['changeAmount']++;
                    $this->proxyMetrics['connectAmount'] = 0;
                    sleep($this->proxyLimits['sleepInterval']);
                }

                if (!is_null($proxy)) {
                    if ($proxy != $options['proxy']) {
                        $request->addOption('proxy', $proxy);
                    }

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

    public function getProxy()
    {
        if ($this->proxy instanceof ProxyInterface) {
            return $this->proxy;
        }

        return null;
    }

    /**
     * @return bool
     */
    protected function isProxyConnectExceed(): bool
    {
        if ($this->proxyMetrics['connectAmount'] >= $this->proxyLimits['connectLimit']) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function isProxyChangeExceed(): bool
    {
        if ($this->proxyMetrics['changeAmount'] >= $this->proxyLimits['changeLimit']) {
            return true;
        }

        return false;
    }
}
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
    protected $proxyCallback;

    const DefaultProxyLimits = [
        'changeLimit'   => 1,
        'connectLimit'  => 2,
        'sleepInterval' => 5,
    ];

    /**
     * HttpClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('proxyLimits', $config) AND !is_array($config['proxyLimits'])) {
            $this->proxyLimits = self::DefaultProxyLimits;
        } elseif (array_key_exists('proxyLimits', $config)) {
            $this->proxyLimits = $config['proxyLimits'] + self::DefaultProxyLimits;
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

    public function getProxyCallbackInfo()
    {
        if ($this->proxyCallback instanceof ProxyCallbackInterface) {
            return $this->proxyCallback->getInfo();
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
        if (!array_key_exists('handler', $options)) {
            $options['handler'] = HandlerStack::create();
        }
        $options['handler']->unshift(Response::modifyResponse($response));

        try {
            $response = $this->client->request($request->getMethod(), $request->getUri(), $options);
            $this->proxyMetrics = [
                'change'  => 0,
                'connect' => 0,
            ];

            return $response;
        } catch (ConnectException $e) {
            if (array_key_exists('proxy', $options) OR array_key_exists('proxyCallback', $options)) {
                $proxy = null;
                if (!$this->isProxyConnectExceed()) {
                    $proxy = $options['proxy'];
                    $this->proxyMetrics['connectAmount']++;
                    sleep($this->proxyLimits['sleepInterval']);
                } elseif ($options['proxyCallback'] instanceof ProxyCallbackInterface AND !$this->isProxyChangeExceed()) {
                    if (!is_null($this->proxyCallback)) {
                        sleep($this->proxyLimits['sleepInterval']);
                        $this->proxyMetrics['changeAmount']++;
                    }

                    $this->proxyCallback = $options['proxyCallback'];
                    $proxy = $this->proxyCallback->getProxy();
                }

                if (!is_null($proxy)) {
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
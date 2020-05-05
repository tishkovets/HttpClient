<?php

namespace HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;

class HttpClient
{
    protected $config;
    protected $client;
    protected $connectMetrics = [
        'proxyChanges' => 0,
        'attempts'     => 0,
    ];
    protected $connectLimits = [];
    protected $proxy;

    const DefaultConnectLimits = [
        'proxyChanges' => 0,
        'attempts'     => 1,
        'sleep'        => 0,
    ];

    /**
     * HttpClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('cookies', $config) AND is_array($config['cookies'])) {
            $config['cookies'] = new CookieJar(false, $config['cookies']);
        } elseif (!(array_key_exists('cookies', $config) AND $config['cookies'] instanceof CookieJar)) {
            $config['cookies'] = new CookieJar(false, []);
        }

        $this->client = new Client(['cookies' => $config['cookies']]);
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
     */
    public function setConfig(array $config)
    {
        if (array_key_exists('cookies', $config) AND is_array($config['cookies'])) {
            $config['cookies'] = new CookieJar(false, $config['cookies']);
        }

        $this->config = $config;
    }

    public function setConfigKey($key, $value)
    {
        $this->config[$key] = $value;
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

        if (array_key_exists('connectLimits', $options) AND !is_array($options['connectLimits'])) {
            $this->connectLimits = self::DefaultConnectLimits;
        } elseif (array_key_exists('connectLimits', $options)) {
            $this->connectLimits = $options['connectLimits'] + self::DefaultConnectLimits;
        }


        try {
            $response = $this->client->request($request->getMethod(), $request->getUri(), $options);
            $this->connectMetrics['attempts'] = 0;

            return $response;
        } catch (ConnectException $e) {
            $retry = false;
            $this->connectMetrics['attempts']++;
            if (array_key_exists('proxy', $options)) {
                $proxy = null;

                if (!$this->isProxyConnectExceed()) {
                    $proxy = $options['proxy'];
                    sleep($this->connectLimits['sleep']);
                } elseif ($this->proxy instanceof ProxyInterface AND !$this->isProxyChangeExceed()) {
                    $proxy = $this->proxy->assignProxy();
                    $this->connectMetrics['proxyChanges']++;
                    $this->connectMetrics['attempts'] = 0;
                    sleep($this->connectLimits['sleep']);
                }

                if (!is_null($proxy)) {
                    if ($proxy != $options['proxy']) {
                        $request->addOption('proxy', $proxy);
                    }
                    $retry = true;
                }
            } elseif (!$this->isProxyConnectExceed()) {
                $retry = true;
                sleep($this->connectLimits['sleep']);
            }

            if ($retry) {
                return $this->send($request, $response);
            }

            throw $e;
        }
    }

    public function getCookies()
    {
        $cookies = [];
        if ($this->getConfigOption('cookies') instanceof CookieJar) {
            #if ($this->client->getConfig('cookies') instanceof CookieJar) {
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

    public function getProxy()
    {
        if ($this->proxy instanceof ProxyInterface) {
            return $this->proxy;
        }

        return null;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @return bool
     */
    protected function isProxyConnectExceed(): bool
    {
        if ($this->connectMetrics['attempts'] >= $this->connectLimits['attempts']) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function isProxyChangeExceed(): bool
    {
        if ($this->connectMetrics['proxyChanges'] >= $this->connectLimits['proxyChanges']) {
            return true;
        }

        return false;
    }
}
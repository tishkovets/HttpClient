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
    public Client $guzzle;
    protected array $config;
    protected array $connectMetrics = [
        'attempts' => 0,
    ];
    protected array $connectLimits = [];

    const DEFAULT_CONNECT_LIMITS = [
        'attempts' => 1,
        'sleep'    => 0,
    ];

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
            $response = new Response();
        }

        $options = $request->getOptions();
        if (array_key_exists('connectLimits', $options) and !is_array($options['connectLimits'])) {
            $this->connectLimits = self::DEFAULT_CONNECT_LIMITS;
        } elseif (array_key_exists('connectLimits', $options)) {
            $this->connectLimits = $options['connectLimits'] + self::DEFAULT_CONNECT_LIMITS;
        }


        try {
            $r = $this->guzzle->request($request->getMethod(), $request->getUri(), $options);
            $this->connectMetrics['attempts'] = 0;

            return $response($r, $request->getBaseUri());
        } catch (ConnectException $e) {
            $this->connectMetrics['attempts']++;
            if ($this->connectMetrics['attempts'] < $this->connectLimits['attempts']) {
                return $this->send($request, $response);
            }

            throw $e;
        }
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
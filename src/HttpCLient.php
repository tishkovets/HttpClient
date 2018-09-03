<?php

namespace HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use HttpClient\Exception\HttpClientException;

class HttpClient
{
    protected $client;
    protected $getProxyCallback;
    protected $proxyData;
    protected $loop;
    protected $attempt;
    protected $proxyChangeLimits = [];

    const MAX_LOOPS = 10;
    CONST MAX_ATTEMPTS = 3;
    CONST SLEEP_ATTEMPTS = 5;

    public function __construct(array $guzzleOptions, callable $getProxyCallback = null, array $proxyChangeLimits = [])
    {
        $this->client = new Client($guzzleOptions);
        $this->getProxyCallback = $getProxyCallback;

        $this->loop = 0;
        $this->attempt = 0;

        if (sizeof($proxyChangeLimits) > 0) {
            if (array_key_exists('loopMax', $proxyChangeLimits)) {
                $this->proxyChangeLimits['loopMax'] = $proxyChangeLimits['loopMax'];
            }

            if (array_key_exists('attemptMax', $proxyChangeLimits)) {
                $this->proxyChangeLimits['attemptMax'] = $proxyChangeLimits['attemptMax'];
            }

            if (array_key_exists('attemptSleep', $proxyChangeLimits)) {
                $this->proxyChangeLimits['attemptSleep'] = $proxyChangeLimits['attemptSleep'];
            }
        }

        if (!is_null($this->getProxyCallback)) {
            if (!array_key_exists('loopMax', $this->proxyChangeLimits)) {
                $this->proxyChangeLimits['loopMax'] = self::MAX_LOOPS;
            }
            if (!array_key_exists('attemptMax', $this->proxyChangeLimits)) {
                $this->proxyChangeLimits['attemptMax'] = self::MAX_ATTEMPTS;
            }
            if (!array_key_exists('attemptSleep', $this->proxyChangeLimits)) {
                $this->proxyChangeLimits['attemptSleep'] = self::SLEEP_ATTEMPTS;
            }
        } else {
            $this->proxyChangeLimits = [
                'loopMax'      => 1,
                'attemptMax'   => 1,
                'attemptSleep' => 0,
            ];
        }
    }

    public function getProxyData($option = null)
    {
        if (is_null($option)) {
            return $this->proxyData;
        } else {
            return $this->proxyData[$option];
        }
    }

    /**
     * @throws HttpClientException
     */
    public function fetchProxyData()
    {
        if ($this->loop >= $this->proxyChangeLimits['loopMax']) {
            throw new HttpClientException('Connect attempts exceeded');
        } else {
            $this->proxyData = call_user_func($this->getProxyCallback);
            if (!array_key_exists('proxy', $this->proxyData) OR !array_key_exists('type', $this->proxyData)) {
                throw new HttpClientException('getProxyCallback error');
            }
        }
    }

    /**
     * @param        $method
     * @param string $uri
     * @param array  $options
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws HttpClientException
     */
    public function request($method, $uri = '', array $options = []): Response
    {
        if ($this->loop >= $this->proxyChangeLimits['loopMax']) {
            throw new HttpClientException('Connect attempts exceeded');
        } elseif (!is_null($this->getProxyCallback) AND !array_key_exists('proxy', $this->getProxyData())) {
            $this->fetchProxyData();
        }


        if ($this->attempt >= $this->proxyChangeLimits['attemptMax']) {
            $this->loop++;
            $this->attempt = 0;
            $this->fetchProxyData();

            return $this->request($method, $uri, $options);
        } else {
            $this->attempt++;
        }
        try {
            if (!is_null($this->getProxyCallback)) {
                if ($this->getProxyData('type') == 'https') {
                    $options['proxy'] = $this->getProxyData('proxy');
                } elseif ($this->getProxyData('type') == 'socks5') {
                    $options['proxy'] = 'socks5://' . $this->getProxyData('proxy');
                } else {
                    throw new HttpClientException('Unknown proxy type');
                }
            }
            $response = call_user_func_array([$this->client, 'request'], [$method, $uri, $options]);
            $this->loop = 0;
            $this->attempt = 0;

            return $response;
        } catch (ConnectException $e) { # сработал timeout или connect_timeout.
            sleep($this->proxyChangeLimits['attemptSleep']);

            return $this->request($method, $uri, $options);
        }
    }

    public function getCookies()
    {
        $cookies = [];
        if ($this->client->getConfig('cookies') instanceof CookieJar) {
            $cookieArray = array_map(function ($array) {
                return array_diff($array, [false, null]);
            }, $this->client->getConfig('cookies')->toArray());

            foreach ($cookieArray as $cookie) { # удаление устаревших кукисов
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


    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->client, $name], $arguments);
    }
}
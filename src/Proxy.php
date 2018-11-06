<?php

namespace HttpClient;

use HttpClient\Exception\ProxyException;

class Proxy implements ProxyInterface
{
    protected $callback;
    protected $proxy;
    protected $info;

    /**
     * Proxy constructor.
     *
     * @param callable $callback
     *
     * @throws ProxyException
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->assignProxy();
    }

    /**
     * @throws ProxyException
     */
    public function assignProxy()
    {
        $proxyData = call_user_func($this->callback);
        if (!array_key_exists('proxy', $proxyData)) {
            throw new ProxyException();
        }

        $this->proxy = $proxyData['proxy'];
        unset($proxyData['proxy']);
        $this->info = $proxyData;

        return $this->proxy;
    }

    public function getProxy()
    {
        return $this->proxy;
    }

    public function getInfo($option = null)
    {
        if (is_null($option)) {
            return $this->info;
        } else {
            return @$this->info[$option];
        }
    }
}
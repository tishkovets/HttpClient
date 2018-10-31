<?php

namespace HttpClient;

interface ProxyCallbackInterface
{
    public function getProxy();

    public function getInfo();
}
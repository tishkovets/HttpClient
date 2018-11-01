<?php

namespace HttpClient;

interface ProxyInterface
{
    public function assignProxy();

    public function getProxy();

    public function getInfo();
}
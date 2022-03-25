<?php

namespace HttpClient;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

class Response
{
    protected ResponseInterface $response;
    protected string $baseUri;

    public function getParentResponse(): ResponseInterface
    {
        return $this->response;
    }


    public function isJson(): bool
    {
        return isJson($this->getBody());

    }

    public function parseJson($assoc = false)
    {
        return json_decode($this->getBody(), $assoc);

    }

    /**
     * Получение raw тела ответа
     *
     * @return string
     */
    public function getBody(): string
    {
        return (string)$this->getParentResponse()->getBody();
    }

    /**
     * Получение редеректа
     *
     * @return null|string
     */
    public function getRedirect(): ?string
    {
        $redirect = $this->getParentResponse()->getHeaderLine('Location');
        if ($redirect) {
            if (strpos($redirect, 'http') !== 0) {
                $p = parse_url($this->baseUri);
                $redirect = $p['scheme'] . '://' . $p['host'] . (strpos($redirect, '/') !== 0 ? '/' : '') . $redirect;
            }

            return $redirect;
        } else {
            return null;
        }
    }

    public function convertEncoding($from, $to): Response
    {
        $body = Utils::streamFor(mb_convert_encoding($this->getBody(), $to, $from));
        $this->response = $this->response->withBody($body);

        return $this;
    }

    public function __invoke(ResponseInterface $response, $baseUri): Response
    {
        $this->baseUri = $baseUri;
        $this->response = $response;

        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->response, $name)) {
            return call_user_func_array([$this->response, $name], $arguments);
        }

        trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
    }
}
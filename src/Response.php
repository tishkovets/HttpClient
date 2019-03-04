<?php

namespace HttpClient;

use \GuzzleHttp\Psr7\Request as GuzzleRequest;
use \GuzzleHttp\Psr7\Response as GuzzleResponse;
use function GuzzleHttp\Psr7\stream_for;

class Response
{
    protected $request;
    protected $response;

    public function getParentRequest(): GuzzleRequest
    {
        return $this->request;
    }

    public function getParentResponse(): GuzzleResponse
    {
        return $this->response;
    }


    public function isJson()
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
    public function getBody()
    {
        return (string)$this->getParentResponse()->getBody();
    }

    /**
     * Получение редеректа
     *
     * @return null|string
     */
    public function getRedirect()
    {
        $redirect = $this->getParentResponse()->getHeaderLine('Location');
        if ($redirect) {
            if (strpos($redirect, 'http') !== 0) {
                $p = parse_url($this->getParentRequest()->getUri()->__toString());
                $redirect = $p['scheme'] . '://' . $p['host'] . (strpos($redirect, '/') !== 0 ? '/' : '') . $redirect;
            }

            return $redirect;
        } else {
            return null;
        }
    }

    public function convertEncoding($from, $to)
    {
        $this->response = $this->response->withBody(stream_for(mb_convert_encoding($this->getBody(), $to, $from)));

        return $this;
    }

    public function __invoke(GuzzleRequest $request, GuzzleResponse $response): Response
    {
        $this->request = $request;
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

    /**
     * @param Response $finalResponse
     *
     * @return \Closure
     */
    public static function modifyResponse(Response $finalResponse)
    {
        return function (callable $handler) use ($finalResponse) {
            return function (GuzzleRequest $request, array $options) use ($handler, $finalResponse) {
                return $handler($request, $options, $finalResponse)->then(
                    function (GuzzleResponse $response) use ($request, $finalResponse) {
                        return $finalResponse($request, $response);
                    }
                );
            };
        };
    }
}
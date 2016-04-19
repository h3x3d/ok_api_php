<?php

namespace H3x3d\Odnoklassniki;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class Api.
 */
class Api
{
    const API_BASE = 'http://api.ok.ru/fb.do';
    const OAUTH_BASE = 'http://api.ok.ru/oauth/token.do';

    /**
     * @var string
     */
    private $app;
    /**
     * @var string
     */
    private $publicKey;
    /**
     * @var string
     */
    private $secretKey;
    /**
     * @var string
     */
    private $accessToken;
    /**
     * @var Client
     */
    private $refreshToken;
    /**
     * @var Client
     */
    private $client;

    /**
     * @param string $app
     * @param string $publicKey
     * @param string $secretKey
     * @param string $accessToken
     * @param string $refreshToken
     * @param array  $httpParams
     */
    public function __construct($app, $publicKey, $secretKey, $accessToken = null, $refreshToken = null, $httpParams = [])
    {
        $this->app = $app;
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->client = new Client(array_merge($httpParams, ['headers' => [
            'Accept' => 'application/json',
        ]]));
    }

    /**
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @return mixed
     */
    public function refresh()
    {
        $response = $this->call(self::OAUTH_BASE, [
            'grant_type' => 'refresh_token',
            'client_id' => $this->app,
            'client_secret' => $this->secretKey,
            'refresh_token' => $this->refreshToken,
        ]);

        $this->accessToken = $response['access_token'];

        return $this->accessToken;
    }

    /**
     * @param $method
     * @param $params
     * @param $access_token
     *
     * @return mixed
     *
     * @throws ApiException
     */
    public function api($method, array $args = [])
    {
        $params = array_merge($args, [
            'application_key' => $this->publicKey,
            'method' => $method,
        ]);

        $params['sig'] = $this->sign($params);

        if ($this->accessToken) {
            $params['access_token'] = $this->accessToken;
        }

        try {
            return $this->call(self::API_BASE, $params);
        } catch (ApiException $e) {
            if ($e->getCode() == ApiException::PARAM_SESSION_EXPIRED
                && !empty($this->refreshToken)) {
                $this->refresh();

                return $this->api($method, $args);
            }
            throw $e;
        }
    }

    /**
     * @param $params
     * @param $access_token
     *
     * @return string
     */
    public function sign(array $params)
    {
        $sign = '';
        ksort($params);
        foreach ($params as $key => $value) {
            if ('sig' == $key || 'resig' == $key) {
                continue;
            }
            $sign .= $key . '=' . $value;
        }

        $sign .= empty($this->accessToken) ?
            $this->secretKey :
            md5($this->accessToken . $this->secretKey);

        var_dump($sign);

        return md5($sign);
    }

    /**
     * @param $params
     *
     * @return mixed
     *
     * @throws ApiException
     */
    private function call($url, array $params)
    {
        var_dump($params);
        try {
            $response = $this->client->post($url, [
                'query' => $params,
                'form' => $params,
            ])->getBody();

            if (!$response = json_decode($response, true)) {
                throw new ApiException('ResponseParseError');
            }
            if (!empty($response['error_code']) && !empty($response['error_msg'])) {
                throw new ApiException($response['error_msg'],
                    $response['error_code']);
            }

            return $response;
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage());
        }
    }
}

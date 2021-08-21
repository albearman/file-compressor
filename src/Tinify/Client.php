<?php

declare(strict_types=1);

namespace Bearman\FileCompressor\Tinify;

use Bearman\FileCompressor\Exception\EmptyKeyException;
use ReflectionClass;
use Tinify\AccountException;
use Tinify\Client as TinifyClient;
use Tinify\ClientException;
use Tinify\ConnectionException;
use Tinify\Exception;
use Tinify\ServerException;
use Tinify\Tinify;

class Client
{
    public const API_CRAZY_ENDPOINT = 'https://tinypng.com/web';
    public const API_ENDPOINT = "https://api.tinify.com";

    public const RETRY_COUNT = 1;
    public const RETRY_DELAY = 500;

    /** @var array */
    private $options;

    /** @var string */
    private $key;

    /**
     * Client constructor.
     *
     * @param string $key
     *
     * @throws ClientException
     */
    public function __construct($key = 'crazy')
    {
        $curl = curl_version();

        if (!($curl["features"] & CURL_VERSION_SSL)) {
            throw new ClientException(
                "Your curl version does not support secure connections"
            );
        }

        if ($curl["version_number"] < 0x071201) {
            $version = $curl["version"];
            throw new ClientException(
                "Your curl version ${version} is outdated; please upgrade to 7.18.1 or higher"
            );
        }

        $this->key = $key;

        $this->options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_CAINFO         => $this->caBundle(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => $this->userAgent()
        );
    }

    private function caBundle(): string
    {
        $reflectionClientClass = new ReflectionClass(TinifyClient::class);
        return dirname(
            $reflectionClientClass->getFileName()
        ) . "/../data/cacert.pem";
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) '
            . 'AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36';
    }

    /**
     * @param string            $method
     * @param string            $url
     * @param null|array|string $body
     *
     * @return object
     * @throws ClientException
     * @throws ConnectionException
     * @throws EmptyKeyException
     * @throws Exception
     * @throws AccountException
     * @throws ServerException
     */
    public function request(string $method, string $url, $body = null): object
    {
        $fileOver5Mb = is_string($body)
            ? mb_strlen($body, '8bit') > '5242880'
            : false;

        $url = $this->prepareUrl($url, $fileOver5Mb);

        $header = [];

        if (is_array($body)) {
            if (empty($body) === false) {
                $body = json_encode($body);
                array_push($header, "Content-Type: application/json");
            }
        }

        for ($retries = self::RETRY_COUNT; $retries >= 0; $retries--) {
            if ($retries < self::RETRY_COUNT) {
                usleep(self::RETRY_DELAY * 1000);
            }

            $request = curl_init();
            if ($request == false) {
                throw new ConnectionException(
                    "Error while connecting: curl extension is not functional or disabled."
                );
            }

            curl_setopt($request, CURLOPT_URL, $url);
            curl_setopt($request, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt_array($request, $this->options);

            if (count($header) > 0) {
                curl_setopt($request, CURLOPT_HTTPHEADER, $header);
            }

            if (empty($body) === false) {
                curl_setopt($request, CURLOPT_POSTFIELDS, $body);
            }

            $response = curl_exec($request);

            if (is_string($response)) {
                $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
                $headerSize = curl_getinfo($request, CURLINFO_HEADER_SIZE);
                curl_close($request);

                $headers = $this->parseHeaders(
                    substr($response, 0, $headerSize)
                );

                $body = substr($response, $headerSize);

                if (isset($headers["compression-count"])) {
                    Tinify::setCompressionCount(
                        intval($headers["compression-count"])
                    );
                }

                if ($status >= 200 && $status <= 299) {
                    return (object)[
                        "body"    => $body,
                        "headers" => $headers
                    ];
                }

                $details = json_decode($body);
                if (!$details) {
                    $message = sprintf(
                        "Error while parsing response: %s (#%d)",
                        PHP_VERSION_ID >= 50500 ? json_last_error_msg()
                            : "Error",
                        json_last_error()
                    );
                    $details = (object)[
                        "message" => $message,
                        "error"   => "ParseError"
                    ];
                }

                if ($retries > 0 && $status >= 500) {
                    continue;
                }
                throw Exception::create(
                    $details->message,
                    $details->error,
                    $status
                );
            } else {
                $message = sprintf(
                    "%s (#%d)",
                    curl_error($request),
                    curl_errno($request)
                );
                curl_close($request);
                if ($retries > 0) {
                    continue;
                }
                throw new ConnectionException(
                    "Error while connecting: " . $message
                );
            }
        }
        throw new ClientException(
            "The client did not fulfill any requests"
        );
    }

    /**
     * @param string $url
     * @param bool   $bigFile
     *
     * @return string
     * @throws EmptyKeyException
     */
    private function prepareUrl(string $url, bool $bigFile = false): string
    {
        if ($bigFile === false) {
            $url = strtolower(substr($url, 0, 6)) == "https:"
                ? $url
                : self::API_CRAZY_ENDPOINT . $url;
            unset($this->options[CURLOPT_USERPWD]);
        } elseif ($this->key !== 'crazy') {
            $url = strtolower(substr($url, 0, 6)) == "https:"
                ? $url
                : self::API_ENDPOINT . $url;

            $this->options[CURLOPT_USERPWD] = "api:" . $this->key;
        } else {
            throw new EmptyKeyException('TinyPng');
        }

        return $url;
    }

    /**
     * @param string|array $headers
     *
     * @return array
     */
    protected function parseHeaders($headers): array
    {
        if (!is_array($headers)) {
            $headers = explode("\r\n", $headers);
        }

        $output = [];
        foreach ($headers as $header) {
            if (empty($header)) {
                continue;
            }
            $split = explode(":", $header, 2);
            if (count($split) === 2) {
                $output[strtolower($split[0])] = trim($split[1]);
            }
        }
        return $output;
    }
}

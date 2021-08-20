<?php

declare(strict_types=1);

namespace Bearman\FileCompressor\Tinify;

use Bearman\FileCompressor\Exception\EmptyKeyException;
use Bearman\FileCompressor\Exception\ResponseNotFoundException;
use ReflectionClass;
use Tinify\AccountException;
use Tinify\Client;
use Tinify\ClientException;
use Tinify\ConnectionException;
use Tinify\Exception;
use Tinify\ServerException;
use Tinify\Tinify;

class CrazyTinyPngClient
{
    public const API_CRAZY_ENDPOINT = 'https://tinypng.com/web';
    public const API_ENDPOINT = "https://api.tinify.com";

    public const RETRY_COUNT = 1;
    public const RETRY_DELAY = 500;

    /** @var object */
    public $response;

    /** @var array */
    private $options;

    /** @var string */
    private $key;

    /** @var string */
    private $caBundleKey;

    /**
     * CrazyTinyPngClient constructor.
     *
     * @param string $key
     *
     * @throws ClientException
     */
    public function __construct(string $key)
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

        $reflectionClientClass = new ReflectionClass(Client::class);
        $this->caBundleKey = dirname(
            $reflectionClientClass->getFileName()
        );

        $this->options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_USERAGENT      => $this->userAgent()
        ];
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) '
            . 'AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36';
    }

    /**
     * @param string $body
     *
     * @return $this
     * @throws AccountException
     * @throws ClientException
     * @throws ConnectionException
     * @throws EmptyKeyException
     * @throws Exception
     * @throws ServerException
     */
    public function compression(string $body): self
    {
        $url = '/shrink';

        $fileOver5Mb = mb_strlen($body, '8bit') < '5242880';

        if ($fileOver5Mb) {
            $url = strtolower(substr($url, 0, 6)) == "https:"
                ? $url
                : self::API_CRAZY_ENDPOINT . $url;
            unset(
                $this->options[CURLOPT_USERPWD],
                $this->options[CURLOPT_CAINFO],
                $this->options[CURLOPT_SSL_VERIFYPEER]
            );
        } elseif ($this->key !== 'crazy') {
            $url = strtolower(substr($url, 0, 6)) == "https:"
                ? $url
                : self::API_ENDPOINT . $url;

            $options = [
                CURLOPT_USERPWD        => "api:" . $this->key,
                CURLOPT_CAINFO         => $this->caBundleKey,
                CURLOPT_SSL_VERIFYPEER => true
            ];

            $this->options = array_merge($this->options, $options);
        } else {
            throw new EmptyKeyException('TinyPng');
        }

        $this->response = $this->request('post', $body, $url);

        return $this;
    }


    /**
     * @param string            $method
     * @param string|array|null $body
     * @param string            $url
     *
     * @return object
     * @throws AccountException
     * @throws ClientException
     * @throws ConnectionException
     * @throws Exception
     * @throws ServerException
     */
    public function request(
        string $method,
        $body = null,
        string $url = '/shrink'
    ): object {
        $header = [];

        if (is_array($body)) {
            $body = json_encode($body);
            array_push(
                $header,
                'Content-Type: application/json'
            );
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

            $header = array_merge($header, $this->options);

            curl_setopt_array($request, $header);
            curl_setopt($request, CURLOPT_URL, $url);
            curl_setopt($request, CURLOPT_CUSTOMREQUEST, strtoupper($method));


            if (empty($body) === false) {
                curl_setopt($request, CURLOPT_POSTFIELDS, $body);
            }

            $response = curl_exec($request);

            if (is_string($response)) {
                $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
                $headerSize = curl_getinfo(
                    $request,
                    CURLINFO_HEADER_SIZE
                );
                curl_close($request);

                $headers = $this->parseHeaders(
                    substr(
                        $response,
                        0,
                        $headerSize
                    )
                );

                $body = substr($response, $headerSize);

                if (isset($headers["compression-count"])) {
                    Tinify::setCompressionCount(
                        intval($headers["compression-count"])
                    );
                }

                if ($status >= 200 && $status <= 299) {
                    return (object)[
                        'body'    => $body,
                        'headers' => $headers
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
     * @param string|array $headers
     *
     * @return array
     */
    protected function parseHeaders($headers): array
    {
        if (is_array($headers) === false) {
            $headers = explode("\r\n", $headers);
        }

        $resources = [];
        foreach ($headers as $header) {
            if (empty($header)) {
                continue;
            }
            $split = explode(":", $header, 2);
            if (count($split) === 2) {
                $resources[strtolower($split[0])] = trim($split[1]);
            }
        }
        return $resources;
    }

    /**
     * @param string $path
     *
     * @return false|int
     * @throws AccountException
     * @throws ClientException
     * @throws ConnectionException
     * @throws Exception
     * @throws ResponseNotFoundException
     * @throws ServerException
     */
    public function toFile(string $path)
    {
        return file_put_contents($path, $this->toBuffer());
    }

    /**
     * @return string
     * @throws AccountException
     * @throws ClientException
     * @throws ConnectionException
     * @throws Exception
     * @throws ResponseNotFoundException
     * @throws ServerException
     */
    public function toBuffer(): string
    {
        if (empty($this->response)) {
            throw new ResponseNotFoundException();
        }

        $response = $this->request($this->response->header['location']);

        return $response->data;
    }
}

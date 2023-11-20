<?php
namespace App\Utils;

class CurlGet
{
    private $url;
    private $options;

    /**
     * @param string $url     Request URL
     * @param array  $options cURL options
     */
    public function __construct(String $url, String $options = '')
    {
        $this->url = $url;
        $this->options = $options;
    }

    /**
     * Get the response
     * @return string
     * @throws \RuntimeException On cURL error
     */
    public function __invoke(String $getData)
    {
        $ch = \curl_init($this->url . "?" . $getData);


        \curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET' );
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);

        $response = \curl_exec($ch);
        $error    = \curl_error($ch);
        $errno    = \curl_errno($ch);

        if (\is_resource($ch)) {
            \curl_close($ch);
        }

        if (0 !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        return $response;
    }
}

<?php

class HttpClient
{
    private $headers = array(
        'Connection: keep-alive',
        'Accept: text/plain,text/html',
        'User-Agent: Mozilla/5.0 (compatible; ShaarliApiBot/2.0; +https://github.com/mknexen/shaarli-api)',
        'Accept-Encoding: gzip,deflate,br',
    );

    /**
     * Make http request and return html content
     */
    public function makeRequest($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();

            $options = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_AUTOREFERER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_ENCODING => 'gzip',
                CURLOPT_HTTPHEADER => $this->headers,
            );

            curl_setopt_array($ch, $options);

            $html = curl_exec($ch);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);

            return compact('html', 'error', 'errno', 'info');
        } else {
            $opts = array(
              'http' => array( // TODO http / https ?
                'method' => 'GET',
                'header' => implode("\r\n", $this->headers),
                'timeout' => 30,
                'allow_self_signed' => true,
              )
            );

            $context = stream_context_create($opts);

            $html = file_get_contents($url, false, $context);

            // TODO
            var_dump(get_headers());
            var_dump($http_response_header);

            $info = array(
                'http_code' => 200, // TODO HTTP Code returned by the server
                'url' => $url, // TODO Url after redirects
            );

            return compact('html', 'info');
        }
    }

    /**
     * Make http request and return html content
     */
    public function getContent($url)
    {
        $results = $this->makeRequest($url);

        return $results['html'];
    }
}

<?php

class Curl {

	public function __construct() {

		if( !function_exists('curl_init') )
			throw new \Exception("php-curl is required", 1);
	}

	/**
	 * Make http request and return html content
	 */
	public function makeRequest( $url ) {

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
			CURLOPT_HTTPHEADER => array(
	            'Connection: keep-alive',
	            'Accept: text/plain,text/html',
	            'User-Agent: Mozilla/5.0 (compatible; ShaarliApiBot/2.0; +https://github.com/mknexen/shaarli-api)',
	            'Accept-Encoding: gzip,deflate,br',
			),
		);

		curl_setopt_array($ch, $options);

		$html = curl_exec($ch);
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		$info = curl_getinfo($ch);

		return compact('html', 'error', 'errno', 'info');
	}

	/**
	 * Make http request and return html content
	 */
	public function getContent( $url ) {

		$results = $this->makeRequest($url);

		return $results['html'];
	}
}
<?php namespace simplehtmldom;

/**
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 *
 * Licensed under The MIT License
 * See the LICENSE file in the project root for more information.
 *
 * Authors:
 *   S.C. Chen
 *   John Schlick
 *   Rus Carroll
 *   logmanoriginal
 *
 * Contributors:
 *   Yousuke Kumakura
 *   Vadim Voituk
 *   Antcs
 *
 * Version $Rev$
 */

include_once 'HtmlDocument.php';

class HtmlWeb {

	/**
	 * @return HtmlDocument Returns the DOM for a webpage
	 * @return null Returns null if the cURL extension is not loaded and allow_url_fopen=Off
	 * @return null Returns null if the provided URL is invalid (not PHP_URL_SCHEME)
	 * @return null Returns null if the provided URL does not specify the HTTP or HTTPS protocol
	 */
	function load($url)
	{
		if(!filter_var($url, FILTER_VALIDATE_URL)) {
			return null;
		}

		if($scheme = parse_url($url, PHP_URL_SCHEME)) {
			switch(strtolower($scheme)) {
				case 'http':
				case 'https': break;
				default: return null;
			}

			if(extension_loaded('curl')) {
				return $this->load_curl($url);
			} elseif(ini_get('allow_url_fopen')) {
				return $this->load_fopen($url);
			} else {
				error_log(__FUNCTION__ . ' requires either the cURL extension or allow_url_fopen=On in php.ini');
			}
		}

		return null;
	}

    function load_post($url,$header,$post) {
        if(!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        if($scheme = parse_url($url, PHP_URL_SCHEME)) {
            switch(strtolower($scheme)) {
                case 'http':
                case 'https': break;
                default: return null;
            }

            if(extension_loaded('curl')) {
                return $this->load_curl($url,$header,"POST",$post);
            } else {
                error_log(__FUNCTION__ . ' requires the cURL extension in php.ini');
            }
        }

        return null;

    }

	/**
	 * cURL implementation of load
	 */
	private function load_curl($url,$curl_headers=[],$method="GET",$fields=[])
	{
        // There is no guarantee this request will be fulfilled
        $header = array(
            'Accept: text/html', // Prefer HTML format
            'Accept-Charset: utf-8', // Prefer UTF-8 encoding
	    'Accept-Encoding: gzip, deflate',
	    'Accept-Language: en-US,en;q=0.5',
	    'Cache-Control: no-cache',
	    'Content-Type: text/html; charset=UTF-8',
	    'Referer: http://www.google.com/', //Your referrer address
	    'User-Agent: Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.',
	    'X-MicrosoftAjax: Delta=true'
        );
        $header = array_merge($header,$curl_headers);

        $ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0' );
        if($method==="POST") {
            $header[] = "Content-Type: multipart/form-data";
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        define("COOKIE_FILE", "cookie.txt");
        curl_setopt ($ch, CURLOPT_COOKIEJAR, COOKIE_FILE);
        curl_setopt ($ch, CURLOPT_COOKIEFILE, COOKIE_FILE);

		// There is no guarantee this request will be fulfilled
		// -- https://www.php.net/manual/en/function.curl-setopt.php
		curl_setopt($ch, CURLOPT_BUFFERSIZE, MAX_FILE_SIZE);


		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		$doc = curl_exec($ch);

		if(curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
			return null;
		}

		curl_close($ch);

		if(strlen($doc) > MAX_FILE_SIZE) {
			return null;
		}

		return new HtmlDocument($doc);
	}

	/**
	 * fopen implementation of load
	 */
	private function load_fopen($url)
	{
		// There is no guarantee this request will be fulfilled
		$context = stream_context_create(array('http' => array(
			'header' => array(
				'Accept: text/html', // Prefer HTML format
				'Accept-Charset: utf-8', // Prefer UTF-8 encoding
			),
			'ignore_errors' => true // Always fetch content
		)));

		$doc = file_get_contents($url, false, $context, 0, MAX_FILE_SIZE + 1);

		if(isset($http_response_header)) {
			foreach($http_response_header as $rh) {
				// https://stackoverflow.com/a/1442526
				$parts = explode(' ', $rh, 3);

				if(preg_match('/HTTP\/\d\.\d/', $parts[0])) {
					$code = $parts[1];
				}
			} // Last code is final status

			if(!isset($code) || $code !== '200') {
				return null;
			}
		}

		if(strlen($doc) > MAX_FILE_SIZE) {
			return null;
		}

		return new HtmlDocument($doc);
	}

}

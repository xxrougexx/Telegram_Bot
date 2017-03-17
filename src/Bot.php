<?php
namespace Telegram{
	class Bot{
		private $token;
		public function __construct($token){
			$this->token=$token;
		}
		public function get_updates($offset = null, $limit = null, $long_poll = false){
			$parameters = array();
			if(is_numeric($offset))
				$parameters['offset'] = $offset;
			if(is_numeric($limit) && $limit > 0)
				$parameters['limit'] = $limit;
			if($long_poll === true)
				$long_poll = 60;
			if(is_numeric($long_poll) && $long_poll > 0)
				$parameters['timeout'] = $long_poll;

			$handle = $this->prepare_curl_api_request('https://api.telegram.org/bot' . $this->token .'/getUpdates', 'GET', $parameters, null);

			return $this->perform_telegram_request($handle);
		}
		private function prepare_curl_api_request($url, $method, $parameters = null, $body = null, $headers = null) {
			// Parameter checking
			if(!is_string($url)) {
				\Telegram\Logger::error('URL must be a string', __FILE__);
				return false;
			}
			if($method !== 'GET' && $method !== 'POST') {
				\Telegram\Logger::error('Method must be either GET or POST', __FILE__);
				return false;
			}
			if($method !== 'POST' && $body) {
				\Telegram\Logger::error('Cannot send request body content without POST method', __FILE__);
				return false;
			}
			if(!$parameters) {
				$parameters = array();
			}
			if(!is_array($parameters)) {
				\Telegram\Logger::error('Parameters must be an array of values', __FILE__);
				return false;
			}

			// Complex parameters (i.e., arrays) are encoded as JSON strings
			foreach ($parameters as $key => &$val) {
				if (!is_numeric($val) && !is_string($val)) {
					$val = json_encode($val);
				}
			}

			// Prepare final request URL
			$query_string = http_build_query($parameters);
			if(!empty($query_string)) {
				$url .= '?' . $query_string;
			}

			\Telegram\Logger::info("HTTP request to {$url}", __FILE__);

			// Prepare cURL handle
			$handle = curl_init($url);
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($handle, CURLOPT_USERAGENT, 'Telegram Bot client, UWiClab (https://github.com/UWiClab/TelegramBotSample)');
			if($method === 'POST') {
				curl_setopt($handle, CURLOPT_POST, true);
				if($body) {
					curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
				}
			}
			if(is_array($headers)) {
				curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
			}

			return $handle;
		}
		private function prepare_curl_download_request($url, $output_path) {
			//global $curl_requests_private_data;

			// Parameter checking
			if(!is_string($url)) {
				\Telegram\Logger::error('URL must be a string', __FILE__);
				return false;
			}
			$file_handle = fopen(dirname(__FILE__) . '/' . $output_path, 'wb');
			if($file_handle === false) {
				\Telegram\Logger::error("Cannot write to path {$output_path}", __FILE__);
				return false;
			}

			\Telegram\Logger::info("HTTP download request to {$url}", __FILE__);

			// Prepare cURL handle
			$handle = curl_init($url);
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($handle, CURLOPT_FILE, $file_handle);
			curl_setopt($handle, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($handle, CURLOPT_AUTOREFERER, true);
			curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($handle, CURLOPT_MAXREDIRS, 1);
			curl_setopt($handle, CURLOPT_USERAGENT, 'Telegram Bot client, UWiClab (https://github.com/UWiClab/TelegramBotSample)');

			// Store private data
			/*$uuid = uniqid();
			curl_setopt($handle, CURLOPT_PRIVATE, $uuid);
			$curl_requests_private_data[$uuid] = array(
				'file_handle' => $file_handle
			);*/

			return $handle;
		}
		private function perform_telegram_request($handle) {
			if($handle === false) {
				\Telegram\Logger::error('Failed to prepare cURL handle', __FILE__);
			return false;
			}

			$response = $this->perform_curl_request($handle);
			if($response === false) {
				return false;
			}
			else if($response === true) {
				// Response does not contain response body
				// Fake a successful API call with an empty response
				return array();
			}

			// Everything fine, return the result as object
			$response = json_decode($response, true);
			return $response['result'];
		}
		private function perform_curl_request($handle) {
			global $curl_requests_private_data;

			$response = curl_exec($handle);

			if ($response === false) {
				$errno = curl_errno($handle);
				$error = curl_error($handle);
				\Telegram\Logger::error("Curl returned error $errno: $error", __FILE__);

				curl_close($handle);

				return false;
			}

			$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));

			// Handle private data associated to the request
			/*$private_uuid = curl_getinfo($handle, CURLINFO_PRIVATE);
			if($private_uuid !== false) {
				$private_data = $curl_requests_private_data[$private_uuid];
				if($private_data !== null) {
					// Close file handle
					if($private_data['file_handle']) {
						fclose($private_data['file_handle']);
					}

					unset($curl_requests_private_data[$private_uuid]);
				}
			}
			*/

			curl_close($handle);

			if ($http_code >= 500) {
				\Telegram\Logger::warning('Internal server error', __FILE__);
				return false;
			}
			else if($http_code == 401) {
				\Telegram\Logger::warning('Unauthorized request (check token)', __FILE__);
				return false;
			}
			else if ($http_code != 200) {
				\Telegram\Logger::warning("Request failure with code $http_code ($response)", __FILE__);
				return false;
			}
			else {
				return $response;
			}
		}
		function send_message($chat_id, $message, $parameters = null) {
			$parameters = $this->prepare_parameters($parameters, array(
				'chat_id' => $chat_id,
				'text' => $message
			));

			$handle = $this->prepare_curl_api_request('https://api.telegram.org/bot'.$this->token.'/sendMessage', 'POST', $parameters, null);

			return $this->perform_telegram_request($handle);
		}
		private function prepare_parameters($orig_params, $add_params) {
			if(!$orig_params || !is_array($orig_params)) {
				$orig_params = array();
			}

			if($add_params && is_array($add_params)) {
				foreach ($add_params as $key => &$val) {
					$orig_params[$key] = $val;
				}
			}	

			return $orig_params;
		}
		function send_location($chat_id, $latitude, $longitude, $parameters = null) {
			if(!is_numeric($latitude) || !is_numeric($longitude)) {
				\Telegram\Logger:error('Latitude and longitude must be numbers', __FILE__);
				return false;
			}

			$parameters = $this->prepare_parameters($parameters, array(
				'chat_id' => $chat_id,
				'latitude' => $latitude,
				'longitude' => $longitude
			));

			$handle = $this->prepare_curl_api_request('https://api.telegram.org/bot'.$this->token . '/sendLocation', 'POST', $parameters, null);

			return $this->perform_telegram_request($handle);
		}
		function send_photo($chat_id, $photo_id, $caption, $parameters = null) {
			if(!$photo_id) {
				\Telegram\Logger::error('Path to attached photo must be set', __FILE__);
				return false;
			}
			// Photo is remote if URL or non-existing file identifier is used
			$is_remote =(stripos($photo_id, 'http') === 0) || !file_exists($photo_id);

			$parameters = $this->prepare_parameters($parameters, array(
				'chat_id' => $chat_id,
				'caption' => $caption
			));

			$handle = $this->prepare_curl_api_request( 'https://api.telegram.org/bot'.$this->token. '/sendPhoto', 'POST', $parameters, array(
				'photo' => ($is_remote) ? $photo_id : new CURLFile($photo_id)
			));

			return $this->perform_telegram_request($handle);
		}
		function telegram_send_chat_action($chat_id, $action = 'typing') {
			$parameters = array(
				'chat_id' => $chat_id,
				'action' => $action
			);

			$handle = $this->prepare_curl_api_request( 'https://api.telegram.org/bot'.$this->token. '/sendChatAction', 'POST', $parameters, null);

			return $this->perform_telegram_request($handle);
		}
		function starts_with($text = '', $substring = '') {
			return (strpos(mb_strtolower($text), mb_strtolower($substring)) === 0);
		}
		function download_file($file_path, $output_path) {
			$handle = $this->prepare_curl_download_request('https://api.telegram.org/file/bot' . $this->token . '/' . $file_path, $output_path);
			return ($this->perform_telegram_request($handle) !== false);
		}
		function get_file_info($file_id) {
			$parameters = array(
				'file_id' => $file_id
			);

			$handle = $this->prepare_curl_api_request('https://api.telegram.org/bot' .$this->token . '/getFile', 'POST', $parameters, null);

			return $this->perform_telegram_request($handle);
		}
	}
}
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Biometrics API client quizaccess_videocapture.
 *
 * @package    quizaccess_videocapture
 * @copyright  2022 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_videocapture\videocapture;
defined('MOODLE_INTERNAL') || die();


/**
 * Biometrics API client.
 *
 * @package    quizaccess_videocapture
 * @copyright  2020 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class biometrics_api_client {
	
	private $api_user;
	private $api_password;
	private $api_url;
	
	public function __construct($config){
		$this->api_user = $config->api_user;
		$this->api_password = $config->api_password;
		$this->api_url = $config->api_url;
	}

	public function compare_faces(array $faces){
		$url = $this->api_url.'/faces/compareFaces';
		return $this->make_authenticated_call($url, $faces);
	}
	
	public function check_id_picture(array $faces){
		$url = $this->api_url.'/faces/checkIdPicture';
		return $this->make_authenticated_call($url, $faces);
	}	
	
	private function make_authenticated_call($url, $data = null){
		$token_res = $this->getToken();
		if(json_decode($token_res)->error){
			return $token_res;
		}else{
			$token = json_decode($token_res)->token;
		}
		return $this->make_call($url, $data, $token);
	}
	
	private function getToken(){
		global $DB;

		if($DB->count_records('quizaccess_videocap_tokens') > 0){
			$sql = 'select * from {quizaccess_videocap_tokens} where 1 = 1 limit 1';
			$token = $DB->get_record_sql($sql);
			if(time() < ($token->timeexpires - 30)){
				return json_encode(['error'=>false, 'token'=>$token->token]);
			}
		}

		$login_result = json_decode($this->loginToApi());
		if($login_result->error){
			return json_encode($login_result);
		}
		
		if(is_null($token)){
			$token = new \stdClass();
		}
		$token->token = $login_result->access_token;
		$token->timecreated = time();
		$token->timeexpires = time() + $login_result->expires_in;
		
		if(is_null($token->id)){
			$DB->insert_record('quizaccess_videocap_tokens', $token);
		}else{
			$DB->update_record('quizaccess_videocap_tokens', $token);
		}
		
		return json_encode(['error'=>false, 'token'=>$login_result->access_token]);
	}

	private function loginToApi(){
		$url = $this->api_url.'/auth/login';
		$data = ['password'=>$this->api_password,
				 'email'=>$this->api_user];
		return $this->make_call($url, $data);
	}

	
	private function make_call($url, $data = null, $token = null){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		$headers = array();
		if(!is_null($token)){
			$headers[] = "Authorization: bearer ".$token;
		}
		$headers[] = "Accept: application/json ";
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		if(!$result = curl_exec($ch)){
			throw new \moodle_exception('Connection error: php cURL could not connect to the Abaco Technology Biometrics API', 'block_videocapture');
		}
		
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($httpcode != 200){
			//throw new \moodle_exception('VIDEOCAPTURE ERROR [EXT WS]', 'quizaccess_videocapture');
			$decoded_result = json_decode($result);
			return json_encode(['error'=>true, 'msg'=>$decoded_result->error, 'httpcode'=>$httpcode]);
		}
		
		return $result;
	}
}

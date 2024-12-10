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

namespace quizaccess_videocapture\local\videocapture;

/**
 * Biometrics API client.
 *
 * @package    quizaccess_videocapture
 * @copyright  2020 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class biometrics_api_client {

    /**
     * The API user
     * @var string
     */
    private $apiuser;

    /**
     * The API password
     * @var string
     */
    private $apipassword;

    /**
     * The API url
     * @var string
     */
    private $apiurl;

    /**
     * Class constructor
     *
     * @param object $config The plugin settings
     */
    public function __construct($config) {
        $this->apiuser = $config->api_user;
        $this->apipassword = $config->api_password;
        $this->apiurl = $config->api_url;
    }

    /**
     * Compares two faces
     *
     * @param array $faces Array of base64 encoded images
     * @return string a json structure with the check result
     */
    public function compare_faces(array $faces) {
        $url = $this->apiurl.'/faces/compareFaces';
        return $this->make_authenticated_call($url, $faces);
    }

    /**
     * Checks if it is an ID picture
     *
     * @param array $faces Array of base64 encoded images
     * @return string a json structure with the check result
     */
    public function check_id_picture(array $faces) {
        $url = $this->apiurl.'/faces/checkIdPicture';
        return $this->make_authenticated_call($url, $faces);
    }

    /**
     * Authenticated call to external API
     *
     * @param string $url API endpoint to call
     * @return string a json structure with the call result
     */
    private function make_authenticated_call($url, $data = null) {
        $tokenres = $this->get_token();
        if (json_decode($tokenres)->error) {
            return $tokenres;
        } else {
            $token = json_decode($tokenres)->token;
        }
        return $this->make_call($url, $data, $token);
    }

    /**
     * Function that retrieves the token from the DB
     *
     * @return string a json structure containing the token or the error message
     */
    private function get_token() {
        global $DB;

        if ($DB->count_records('quizaccess_videocap_tokens') > 0) {
            $sql = 'select * from {quizaccess_videocap_tokens} where 1 = 1 limit 1';
            $token = $DB->get_record_sql($sql);
            if (time() < ($token->timeexpires - 30)) {
                return json_encode(['error' => false, 'token' => $token->token]);
            }
        }

        $loginresult = json_decode($this->login_to_api());
        if ($loginresult->error) {
            return json_encode($loginresult);
        }

        if (is_null($token)) {
            $token = new \stdClass();
        }
        $token->token = $loginresult->access_token;
        $token->timecreated = time();
        $token->timeexpires = time() + $loginresult->expires_in;

        if (is_null($token->id)) {
            $DB->insert_record('quizaccess_videocap_tokens', $token);
        } else {
            $DB->update_record('quizaccess_videocap_tokens', $token);
        }

        return json_encode(['error' => false, 'token' => $loginresult->access_token]);
    }

    /**
     * Login to API with the token
     *
     * @return string a json structure containing the login result
     */
    private function login_to_api() {
        global $CFG;
        $domain = substr($CFG->wwwroot, strpos($CFG->wwwroot, '//') + 2);
        $url = $this->apiurl.'/auth/loginv2';
        $data = ['password' => $this->apipassword,
                 'email' => $this->apiuser,
                 'domain' => $domain];
        return $this->make_call($url, $data);
    }

    /**
     * Call to external API
     *
     * @param string $url API endpoint to call
     * @param array $data POST data
     * @param string $token The JWT authorization token
     * @return string a json structure with the call result
     */
    private function make_call($url, $data = null, $token = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $headers = [];
        if (!is_null($token)) {
            $headers[] = "Authorization: bearer ".$token;
        }
        $headers[] = "Accept: application/json ";
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!$result = curl_exec($ch)) {
            throw new \moodle_exception('Connection error: php cURL could not connect to the Abaco Technology Biometrics API',
                                        'block_videocapture');
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode != 200) {
            $decodedresult = json_decode($result);
            return json_encode(['error' => true, 'msg' => $decodedresult->error, 'httpcode' => $httpcode]);
        }

        return $result;
    }
}

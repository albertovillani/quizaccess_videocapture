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
 * External API for quizaccess_videocapture.
 *
 * @package    quizaccess_videocapture
 * @copyright  2022 onwards Abaco Technology  {@link https://www.abacotechnology.com}
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_videocapture\external;
use \quizaccess_videocapture\videocapture\biometrics_api_client;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/videocapture/locallib.php');

/**
 * External API for quizaccess_videocapture.
 *
 * @package    quizaccess_videocapture
 * @copyright  2020 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class videocapture_external extends \external_api {

    /**
     * External function parameters.
     *
     * @return external_function_parameters
     */
    public static function check_snapshot_parameters() {
        return new \external_function_parameters([
            'file' => new \external_value(PARAM_RAW, 'The snapshot'),
			'target' => new \external_value(PARAM_RAW, 'The user profile picture'),
        ]);
    }
	
	public static function check_snapshot($file, $target) {
		$config = get_config('quizaccess_videocapture');
		return self::check_snapshot_relay($file, $config, $target);
	}

    /**
     * Check the webcam snapshot using the Abaco Technology Biometrics relay server.
     *
     * @return array
     */
    private static function check_snapshot_relay($file, $config, $target) {
        global $CFG, $DB, $USER, $SESSION;

        $params = self::validate_parameters(self::check_snapshot_parameters(), ['file' => $file, 'target' => $target]);

		$sourceImagedata = $file;

		if($target == null){
			$context = \context_user::instance($USER->id);
			$file_type = 'image/png';
			if(!$file_record = $DB->get_record('files', array('contextid'=>$context->id, 'component'=>'user', 'filearea'=>'icon', 'filepath'=>'/', 'filename'=>'f1.png'))){
				$file_type = 'image/jpg';
				$file_record = $DB->get_record('files', array('contextid'=>$context->id, 'component'=>'user', 'filearea'=>'icon', 'filepath'=>'/', 'filename'=>'f1.jpg'));
			}

			$fs = get_file_storage();
			$fileinfo = array(
					'component'=>'user',
					'filearea'=>'icon',
					'itemid'=>0,
					'contextid'=>$context->id,
					'filepath'=>'/',
					'filename'=>$file_record->filename);

			$file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
						$fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

			if(!$file){
				$result = 0;
			}else{
				$targetImageData = 'data:'.$file_type.';base64,'.base64_encode($file->get_content());
			}
		}else{
			$targetImageData = $target;
		}
		
		if(!isset($result)){
			$post_data = array('source'=>$sourceImagedata, 'target'=>$targetImageData);
			$biometrics_client = new biometrics_api_client($config);
			$check_result = json_decode($biometrics_client->compare_faces($post_data));
			if($check_result->error){
				$retval['remote_error'] = true;
				$retval['msg'] = $check_result->msg;
				$retval['httpcode'] = $check_result->httpcode;
				$retval['match'] = null;
				$retval['failed_attempt'] = null;
				return $retval;
			}
			$result = $check_result->Similarity;
		}
		
		
		if($result > 0){
			$SESSION->videocapturefails = 0;
		}else{
			if(!isset($SESSION->videocapturefails)){
				$SESSION->videocapturefails = 0;
			}
			$SESSION->videocapturefails++;
		}

        $retval = array();
		$retval['remote_error'] = false;
		$retval['msg'] = null;
		$retval['httpcode'] = '200';
        $retval['match'] = $result;
		$retval['failed_attempt'] = $SESSION->videocapturefails;

        return $retval;
    }
	
    /**
     * External function return definition.
     *
     * @return external_single_structure
     */
    public static function check_snapshot_returns() {
      return new \external_single_structure([
		  'remote_error' => new \external_value(PARAM_BOOL, 'Error calling Abaco Technology Biometric API?'),
		  'httpcode' => new \external_value(PARAM_TEXT, 'HTTP error code from the Abaco Technology Biometric API'),
		  'msg' => new \external_value(PARAM_TEXT, 'Error or success message'),
          'match' => new \external_value(PARAM_FLOAT, 'The match of the snapshot against the user profile picture'),
		  'failed_attempt' => new \external_value(PARAM_INT, 'The number of consecutive failed attempts'),
      ]);
    }
	
	
	/*
	*
	* Check new profile picture
	*
	*/
	
	public static function check_profile_picture_parameters() {
        return new \external_function_parameters([
            'file' => new \external_value(PARAM_RAW, 'The picture'),
			'uid' => new \external_value(PARAM_INT, 'The user id')
        ]);
    }
	
	public static function check_profile_picture($file, $uid) {
		global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::check_profile_picture_parameters(), ['file' => $file, 'uid' => $uid]);
		
		$config = get_config('quizaccess_videocapture');
		
		//$targetImageData = 'data:'.$file_type.';base64,'.base64_encode($file->get_content());
		$post_data = array('imagedata'=>$file, 'fromidonly'=>$config->fromidonly);
		
		$biometrics_client = new biometrics_api_client($config);
		$check_result = json_decode($biometrics_client->check_id_picture($post_data));
		
		if($check_result->error){
			$retval['remote_error'] = true;
			$retval['msg'] = $check_result->msg;
			$retval['httpcode'] = $check_result->httpcode;
			$retval['success'] = false;
			$retval['msgcod'] = null;
			$retval['picture'] = null;
			return $retval;
		}
		
		if(!$check_result->success){
			$retval['remote_error'] = false;
			$retval['httpcode'] = '200';
			$retval['success'] = false;
			$retval['msg'] = $check_result->msg;
			$retval['msgcod'] = $check_result->msgcod;
			$retval['picture'] = null;
			return $retval;
		}
		
		$imagedata = quizaccess_videocapture_decode_image($file);
		
		$ext = ($type=="image/jpg")?".jpg":".png";
		$fileName = time().$ext;
		$directory_path = quizaccess_videocapture_mktempdir($CFG->tempdir.'/', 'faceusrpic_'.$uid.'_');
		$full_path = $directory_path."/".$fileName;
		
		$retval = array();
		
		if(file_put_contents($full_path, $imagedata)===false){
			$retval['success'] = false;
			return $retval;
		}

		$im = imagecreatefrompng($full_path);
		$size = min(imagesx($im), imagesy($im));

		$img_width = imagesx($im);
		$img_height = imagesy($im);
		
		$coord = $check_result->coord;
		$topleftcorner_x = $img_width*($coord->Left-0.06);
		$topleftcorner_y = $img_height*($coord->Top-0.06);
		$width = $img_width*($coord->Width+0.12);
		$height = $img_height*($coord->Height+0.12);

		$im2 = imagecrop($im, ['x' => $topleftcorner_x, 'y' => $topleftcorner_y, 'width' => $width, 'height' => $height]);

		if ($im2 !== FALSE) {
			
			$im3 = imagescale($im2, $width*2);
			
			$cropped_full_path = substr($full_path, 0, strrpos($full_path, "."));
			$cropped_full_path .= "_cropped".$ext;

			ob_start();
			imagepng($im3);
			//$fp = fopen($cropped_full_path, 'w');
			$contets = ob_get_contents();
			//fwrite($fp, $contets);
			//fclose($fp);
			ob_end_clean();
			imagedestroy($im2);
		}
		imagedestroy($im);
		
		$retval['remote_error'] = false;
		$retval['httpcode'] = '200';
		$retval['success'] = true;
		$retval['msg'] = "Ok";
		$retval['msgcod'] = $check_result->msgcod;
		$retval['picture'] = 'data:image/png;base64,'.base64_encode($contets);

        return $retval;
	}
	
    public static function check_profile_picture_returns() {
      return new \external_single_structure([
	  	  'remote_error' => new \external_value(PARAM_BOOL, 'Error calling Abaco Technology Biometric API?'),
		  'httpcode' => new \external_value(PARAM_TEXT, 'HTTP error code from the Abaco Technology Biometric API'),
          'success' => new \external_value(PARAM_BOOL, 'Valid ID and found face picture in ID?'),
		  'msg' => new \external_value(PARAM_TEXT, 'Error or success message'),
		  'msgcod' => new \external_value(PARAM_TEXT, 'Error or success code'),
		  'picture' => new \external_value(PARAM_RAW, 'The new profile picture'),
      ]);
    }	


	/*
	*
	* Check new uploaded profile picture
	*
	*/
	
	public static function check_uploaded_profile_picture_parameters() {
        return new \external_function_parameters([
            'jsonformdata' => new \external_value(PARAM_RAW, 'The picture'),
			'uid' => new \external_value(PARAM_INT, 'The user id')
        ]);
    }
	
	public static function check_uploaded_profile_picture($jsonformdata, $uid) {
		global $CFG, $DB;

        $params = self::validate_parameters(self::check_uploaded_profile_picture_parameters(), ['jsonformdata' => $jsonformdata, 'uid' => $uid]);
		
		
		// Get file from Moodle filesystem
		$serializedformdata = json_decode($params['jsonformdata']);
		$formdata = array();
		parse_str($serializedformdata, $formdata);
		$context = \context_user::instance($uid);

		
		$file_record_sql = "SELECT * from {files} 
							WHERE contextid = :contextid 
							AND component = 'user' 
							AND filearea = 'draft' 
							AND filepath = '/' 
							AND filename <> '.' 
							AND itemid = :itemid";
		$file_record_params = array('contextid' => $context->id, 'itemid' => $formdata['newpicture']);
		$file_record = $DB->get_record_sql($file_record_sql, $file_record_params);
		$file_type = $file_record->mimetype;

		$fs = get_file_storage();
		$fileinfo = array(
				'component'=>'user',
				'filearea'=>'draft',
				'itemid'=> $formdata['newpicture'],
				'contextid'=>$context->id,
				'filepath'=>'/',
				'filename'=>$file_record->filename);

		$file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
					$fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

		if(!$file){
			$retval['remote_error'] = false;
			$retval['httpcode'] = null;
			$retval['success'] = false;
			$retval['msg'] = "COULD NOT FIND UPLOADED FILE";
			$retval['msgcod'] = "BIO-GEN-ERR";
			$retval['picture'] = null;
			return $retval;
		}else{
			$file = 'data:'.$file_type.';base64,'.base64_encode($file->get_content());
		}
		
		$config = get_config('quizaccess_videocapture');
		$post_data = array('imagedata'=>$file, 'fromidonly'=>$config->fromidonly);
		$biometrics_client = new biometrics_api_client($config);
		$check_result = json_decode($biometrics_client->check_id_picture($post_data));
		
		if($check_result->error){
			$retval['remote_error'] = true;
			$retval['msg'] = $check_result->msg;
			$retval['httpcode'] = $check_result->httpcode;
			$retval['success'] = false;
			$retval['msgcod'] = null;
			$retval['picture'] = null;
			return $retval;
		}
		
		
		if(!$check_result->success){
			$retval['remote_error'] = false;
			$retval['httpcode'] = '200';
			$retval['success'] = false;
			$retval['msg'] = $check_result->msg;
			$retval['msgcod'] = $check_result->msgcod;
			$retval['picture'] = null;
			return $retval;
		}
		
		$imagedata = quizaccess_videocapture_decode_image($file);
		
		$ext = ($file_type=="image/jpeg")?".jpg":".png";
		$fileName = time().$ext;
		$directory_path = quizaccess_videocapture_mktempdir($CFG->tempdir.'/', 'faceusrpic_'.$uid.'_');
		$full_path = $directory_path."/".$fileName;
		
		$retval = array();
		
		if(file_put_contents($full_path, $imagedata)===false){
			$retval['success'] = false;
			$retval['remote_error'] = false;
			$retval['httpcode'] = null;
			$retval['msg'] = 'ERROR WRITING ON DISK';
			$retval['msgcod'] = null;
			$retval['picture'] = null;
			return $retval;
		}

		if($ext == '.png'){
			$im = imagecreatefrompng($full_path);
		}else{
			$im = imagecreatefromjpeg($full_path);
		}
		$size = min(imagesx($im), imagesy($im));

		$img_width = imagesx($im);
		$img_height = imagesy($im);
		
		$coord = $check_result->coord;
		$topleftcorner_x = $img_width*($coord->Left-0.06);
		$topleftcorner_y = $img_height*($coord->Top-0.06);
		$width = $img_width*($coord->Width+0.12);
		$height = $img_height*($coord->Height+0.12);

		$im2 = imagecrop($im, ['x' => $topleftcorner_x, 'y' => $topleftcorner_y, 'width' => $width, 'height' => $height]);

		if ($im2 !== FALSE) {
			
			$im3 = imagescale($im2, $width*2);
			
			$cropped_full_path = substr($full_path, 0, strrpos($full_path, "."));
			$cropped_full_path .= "_cropped".$ext;

			ob_start();
			if($ext == '.png'){
				imagepng($im3);
			}else{
				imagejpeg($im3);
			}
			//imagepng($im3);
			//$fp = fopen($cropped_full_path, 'w');
			$contets = ob_get_contents();
			//fwrite($fp, $contets);
			//fclose($fp);
			ob_end_clean();
			imagedestroy($im2);
		}
		imagedestroy($im);
		
		$retval['remote_error'] = false;
		$retval['httpcode'] = '200';
		$retval['success'] = true;
		$retval['msg'] = "Ok - ".$fileName;
		$retval['msgcod'] = $check_result->msgcod;
		$retval['picture'] = 'data:'.$file_type.';base64,'.base64_encode($contets);

        return $retval;
	}
	
    public static function check_uploaded_profile_picture_returns() {
      return new \external_single_structure([
		  'remote_error' => new \external_value(PARAM_BOOL, 'Error calling Abaco Technology Biometric API?'),
		  'httpcode' => new \external_value(PARAM_TEXT, 'HTTP error code from the Abaco Technology Biometric API'),
          'success' => new \external_value(PARAM_BOOL, 'Valid ID and found face picture in ID?'),
		  'msg' => new \external_value(PARAM_TEXT, 'Error or success message'),
		  'msgcod' => new \external_value(PARAM_TEXT, 'Error or success code'),
		  'picture' => new \external_value(PARAM_RAW, 'The new profile picture'),
      ]);
    }	



	/*
	*
	* Save profile picture
	*
	*/
	public static function save_profile_picture_parameters() {
        return new \external_function_parameters([
            'file' => new \external_value(PARAM_RAW, 'The picture'),
			'uid' => new \external_value(PARAM_INT, 'The user id')
        ]);
    }
	
	public static function save_profile_picture($file, $uid){
		global $CFG, $DB;
		require_once($CFG->libdir.'/gdlib.php');
		$params = self::validate_parameters(self::save_profile_picture_parameters(), ['file' => $file, 'uid' => $uid]);
		$imagedata = quizaccess_videocapture_decode_image($file);		
		
		$ext = ($type=="image/jpg")?".jpg":".png";
		$fileName = time().$ext;
		$directory_path = quizaccess_videocapture_mktempdir($CFG->tempdir.'/', 'faceusrpic_'.$uid.'_');
		$full_path = $directory_path."/".$fileName;
		
		$retval = array();
		
		if(file_put_contents($full_path, $imagedata)===false){
			$retval['success'] = false;
			return $retval;
		}
		
		$context = \context_user::instance($uid);
		if($newrev = process_new_icon($context, 'user', 'icon', 0, $full_path)){
			$DB->set_field('user', 'picture', $newrev, array('id'=>$uid));
			$retval['success'] = true;
		}else{
			$retval['success'] = false;
		}
		\core\event\user_updated::create_from_userid($uid)->trigger();
		return $retval;
	}
	
    public static function save_profile_picture_returns() {
      return new \external_single_structure([
          'success' => new \external_value(PARAM_BOOL, 'Picture saved and profile updated?'),
      ]);
    }		


}

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
use quizaccess_videocapture\local\videocapture\biometrics_api_client;
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

    /**
     * Check the webcam snapshot using the Abaco Technology Biometrics API.
     *
     * @param string $file Base 64 encoded snaphost image file
     * @param string $target Base 64 encoded target image file or null if target image is the user's profile picture
     * @return array
     */
    public static function check_snapshot($file, $target) {
        $config = get_config('quizaccess_videocapture');
        return self::check_snapshot_relay($file, $config, $target);
    }

    /**
     * Check the webcam snapshot using the Abaco Technology Biometrics relay server.
     *
     * @param string $file Base 64 encoded snaphost image file
     * @param object $config Plugin settings
     * @param string $target Base 64 encoded target image file or null if target image is the user's profile picture
     * @return array
     */
    private static function check_snapshot_relay($file, $config, $target) {
        global $DB, $USER, $SESSION;

        $params = self::validate_parameters(self::check_snapshot_parameters(), ['file' => $file, 'target' => $target]);
        self::validate_context(\context_system::instance());
        require_sesskey();
        $sourceimagedata = $file;

        if ($target == null) {
            $context = \context_user::instance($USER->id);
            $filetype = 'image/png';
            if (!$filerecord = $DB->get_record('files', ['contextid' => $context->id, 'component' => 'user', 'filearea' => 'icon',
                                               'filepath' => '/', 'filename' => 'f1.png'])) {
                $filetype = 'image/jpg';
                $filerecord = $DB->get_record('files', ['contextid' => $context->id, 'component' => 'user', 'filearea' => 'icon',
                                              'filepath' => '/', 'filename' => 'f1.jpg']);
            }

            $fs = get_file_storage();
            $fileinfo = [
                    'component' => 'user',
                    'filearea' => 'icon',
                    'itemid' => 0,
                    'contextid' => $context->id,
                    'filepath' => '/',
                    'filename' => $filerecord->filename];

            $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                        $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

            if (!$file) {
                $result = 0;
            } else {
                $targetimagedata = 'data:'.$filetype.';base64,'.base64_encode($file->get_content());
            }
        } else {
            $targetimagedata = $target;
        }

        if (!isset($result)) {
            $postdata = ['source' => $sourceimagedata, 'target' => $targetimagedata];
            $biometricsclient = new biometrics_api_client($config);
            $checkresult = json_decode($biometricsclient->compare_faces($postdata));
            if ($checkresult->error) {
                $retval['remote_error'] = true;
                $retval['msg'] = $checkresult->msg;
                $retval['httpcode'] = $checkresult->httpcode;
                $retval['match'] = null;
                $retval['failed_attempt'] = null;
                return $retval;
            }
            $result = $checkresult->Similarity;
        }

        if ($result > 0) {
            $SESSION->videocapturefails = 0;
        } else {
            if (!isset($SESSION->videocapturefails)) {
                $SESSION->videocapturefails = 0;
            }
            $SESSION->videocapturefails++;
        }

        $retval = [];
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
            'match' => new \external_value(PARAM_FLOAT, 'The match of the snapshot against the target picture'),
            'failed_attempt' => new \external_value(PARAM_INT, 'The number of consecutive failed attempts'),
        ]);
    }


    /**
     *
     * External function parameters.
     *
     * @return external_function_parameters
     */
    public static function check_profile_picture_parameters() {
        return new \external_function_parameters([
            'file' => new \external_value(PARAM_RAW, 'The picture'),
            'uid' => new \external_value(PARAM_INT, 'The user id'),
        ]);
    }

    /**
     *
     * Check and crop the new profile picture taken by the user with the webcam
     *
     * @param string $file Base 64 encoded image file
     * @param int $uid User id
     * @return array
     */
    public static function check_profile_picture($file, $uid) {
        global $CFG;

        $params = self::validate_parameters(self::check_profile_picture_parameters(), ['file' => $file, 'uid' => $uid]);
        self::validate_context(\context_system::instance());
        require_sesskey();
        $config = get_config('quizaccess_videocapture');

        $postdata = ['imagedata' => $file, 'fromidonly' => $config->fromidonly];

        $biometricsclient = new biometrics_api_client($config);
        $checkresult = json_decode($biometricsclient->check_id_picture($postdata));

        if ($checkresult->error) {
            $retval['remote_error'] = true;
            $retval['msg'] = $checkresult->msg;
            $retval['httpcode'] = $checkresult->httpcode;
            $retval['success'] = false;
            $retval['msgcod'] = null;
            $retval['picture'] = null;
            return $retval;
        }

        if (!$checkresult->success) {
            $retval['remote_error'] = false;
            $retval['httpcode'] = '200';
            $retval['success'] = false;
            $retval['msg'] = $checkresult->msg;
            $retval['msgcod'] = $checkresult->msgcod;
            $retval['picture'] = null;
            return $retval;
        }

        list($type, $imagedata) = quizaccess_videocapture_decode_image($file);
        $ext = ($type == "image/jpg") ? ".jpg" : ".png";
        $filename = time().$ext;
        $context = \context_user::instance($uid);
        $fileinfo = ['component' => 'quizaccess_videocapture',
                'filearea' => 'userpicture',
                'itemid' => 0,
                'contextid' => $context->id,
                'filepath' => '/',
                'filename' => $filename];

        $fs = get_file_storage();
        $storedfile = $fs->create_file_from_string($fileinfo, $imagedata);

        $directorypath = make_temp_directory('quizaccess_videocapture/'.$uid);
        $fullpath = $directorypath."/".$filename;

        $retval = [];

        if ($storedfile->copy_content_to($fullpath) === false) {
            $retval['success'] = false;
            return $retval;
        }

        $im = imagecreatefrompng($fullpath);
        $size = min(imagesx($im), imagesy($im));

        $imgwidth = imagesx($im);
        $imgheight = imagesy($im);

        $coord = $checkresult->coord;
        $topleftcornerx = $imgwidth * ($coord->Left - 0.06);
        $topleftcornery = $imgheight * ($coord->Top - 0.06);
        $width = $imgwidth * ($coord->Width + 0.12);
        $height = $imgheight * ($coord->Height + 0.12);

        $im2 = imagecrop($im, ['x' => $topleftcornerx, 'y' => $topleftcornery, 'width' => $width, 'height' => $height]);

        if ($im2 !== false) {

            $im3 = imagescale($im2, $width * 2);

            $croppedfullpath = substr($fullpath, 0, strrpos($fullpath, "."));
            $croppedfullpath .= "_cropped".$ext;

            ob_start();
            imagepng($im3);
            $contets = ob_get_contents();
            ob_end_clean();
            imagedestroy($im2);
        }
        imagedestroy($im);

        $storedfile->delete();

        $retval['remote_error'] = false;
        $retval['httpcode'] = '200';
        $retval['success'] = true;
        $retval['msg'] = "Ok";
        $retval['msgcod'] = $checkresult->msgcod;
        $retval['picture'] = 'data:image/png;base64,'.base64_encode($contets);

        return $retval;
    }

    /**
     *
     * External function return definition.
     *
     * @return external_single_structure
     */
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


    /**
     *
     * External function parameters.
     *
     * @return external_function_parameters
     */
    public static function check_uploaded_profile_picture_parameters() {
        return new \external_function_parameters([
            'jsonformdata' => new \external_value(PARAM_RAW, 'The picture'),
            'uid' => new \external_value(PARAM_INT, 'The user id'),
        ]);
    }

    /**
     *
     * Check and crop the new profile picture uploaded by the user
     *
     * @param string $jsonformdata Upload form data in json format
     * @param int $uid User id
     * @return array
     */
    public static function check_uploaded_profile_picture($jsonformdata, $uid) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::check_uploaded_profile_picture_parameters(),
                                             ['jsonformdata' => $jsonformdata, 'uid' => $uid]);
        self::validate_context(\context_system::instance());
        require_sesskey();
        // Get file from Moodle filesystem.
        $serializedformdata = json_decode($params['jsonformdata']);
        $formdata = [];
        parse_str($serializedformdata, $formdata);
        $context = \context_user::instance($uid);

        $filerecordsql = "SELECT * from {files}
                            WHERE contextid = :contextid
                            AND component = 'user'
                            AND filearea = 'draft'
                            AND filepath = '/'
                            AND filename <> '.'
                            AND itemid = :itemid";
        $filerecordparams = ['contextid' => $context->id, 'itemid' => $formdata['newpicture']];
        $filerecord = $DB->get_record_sql($filerecordsql, $filerecordparams);
        $filetype = $filerecord->mimetype;

        $fs = get_file_storage();
        $fileinfo = ['component' => 'user',
                'filearea' => 'draft',
                'itemid' => $formdata['newpicture'],
                'contextid' => $context->id,
                'filepath' => '/',
                'filename' => $filerecord->filename];

        $storedfile = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                    $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

        if (!$storedfile) {
            $retval['remote_error'] = false;
            $retval['httpcode'] = null;
            $retval['success'] = false;
            $retval['msg'] = "COULD NOT FIND UPLOADED FILE";
            $retval['msgcod'] = "BIO-GEN-ERR";
            $retval['picture'] = null;
            return $retval;
        } else {
            $file = 'data:'.$filetype.';base64,'.base64_encode($storedfile->get_content());
        }

        $config = get_config('quizaccess_videocapture');
        $postdata = ['imagedata' => $file, 'fromidonly' => $config->fromidonly];
        $biometricsclient = new biometrics_api_client($config);
        $checkresult = json_decode($biometricsclient->check_id_picture($postdata));

        if ($checkresult->error) {
            $retval['remote_error'] = true;
            $retval['msg'] = $checkresult->msg;
            $retval['httpcode'] = $checkresult->httpcode;
            $retval['success'] = false;
            $retval['msgcod'] = null;
            $retval['picture'] = null;
            return $retval;
        }

        if (!$checkresult->success) {
            $retval['remote_error'] = false;
            $retval['httpcode'] = '200';
            $retval['success'] = false;
            $retval['msg'] = $checkresult->msg;
            $retval['msgcod'] = $checkresult->msgcod;
            $retval['picture'] = null;
            return $retval;
        }

        $ext = ($filetype == "image/jpeg") ? ".jpg" : ".png";
        $filename = time().$ext;
        $directorypath = make_temp_directory('quizaccess_videocapture/'.$uid);
        $fullpath = $directorypath."/".$filename;

        $retval = [];

        if ($storedfile->copy_content_to($fullpath) === false) {
            $retval['success'] = false;
            $retval['remote_error'] = false;
            $retval['httpcode'] = null;
            $retval['msg'] = 'ERROR WRITING ON DISK';
            $retval['msgcod'] = null;
            $retval['picture'] = null;
            return $retval;
        }

        if ($ext == '.png') {
            $im = imagecreatefrompng($fullpath);
        } else {
            $im = imagecreatefromjpeg($fullpath);
        }
        $size = min(imagesx($im), imagesy($im));

        $imgwidth = imagesx($im);
        $imgheight = imagesy($im);

        $coord = $checkresult->coord;
        $topleftcornerx = $imgwidth * ($coord->Left - 0.06);
        $topleftcornery = $imgheight * ($coord->Top - 0.06);
        $width = $imgwidth * ($coord->Width + 0.12);
        $height = $imgheight * ($coord->Height + 0.12);

        $im2 = imagecrop($im, ['x' => $topleftcornerx, 'y' => $topleftcornery, 'width' => $width, 'height' => $height]);

        if ($im2 !== false) {

            $im3 = imagescale($im2, $width * 2);

            $croppedfullpath = substr($fullpath, 0, strrpos($fullpath, "."));
            $croppedfullpath .= "_cropped".$ext;

            ob_start();
            if ($ext == '.png') {
                imagepng($im3);
            } else {
                imagejpeg($im3);
            }

            $contets = ob_get_contents();
            ob_end_clean();
            imagedestroy($im2);
        }
        imagedestroy($im);

        $storedfile->delete();

        $retval['remote_error'] = false;
        $retval['httpcode'] = '200';
        $retval['success'] = true;
        $retval['msg'] = "Ok - ".$filename;
        $retval['msgcod'] = $checkresult->msgcod;
        $retval['picture'] = 'data:'.$filetype.';base64,'.base64_encode($contets);

        return $retval;
    }

    /**
     *
     * External function return definition.
     *
     * @return external_single_structure
     */
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



    /**
     *
     * External function parameters.
     *
     * @return external_function_parameters
     */
    public static function save_profile_picture_parameters() {
        return new \external_function_parameters([
            'file' => new \external_value(PARAM_RAW, 'The picture'),
            'uid' => new \external_value(PARAM_INT, 'The user id'),
        ]);
    }

    /**
     *
     * Save user's new profile picture
     *
     * @param string $file Base 64 encoded image file
     * @param int $uid User id
     * @return array
     */
    public static function save_profile_picture($file, $uid) {
        global $CFG, $DB;
        require_once($CFG->libdir.'/gdlib.php');
        $params = self::validate_parameters(self::save_profile_picture_parameters(), ['file' => $file, 'uid' => $uid]);
        self::validate_context(\context_system::instance());
        require_sesskey();

        list($type, $imagedata) = quizaccess_videocapture_decode_image($file);
        $ext = ($type == "image/jpg") ? ".jpg" : ".png";
        $filename = time().$ext;

        $context = \context_user::instance($uid);
        $fileinfo = ['component' => 'quizaccess_videocapture',
                'filearea' => 'userpicture',
                'itemid' => 0,
                'contextid' => $context->id,
                'filepath' => '/',
                'filename' => $filename];

        $fs = get_file_storage();
        $storedfile = $fs->create_file_from_string($fileinfo, $imagedata);

        $directorypath = make_temp_directory('quizaccess_videocapture/'.$uid);
        $fullpath = $directorypath."/".$filename;

        $retval = [];

        if ($storedfile->copy_content_to($fullpath) === false) {
            $storedfile->delete();
            $retval['success'] = false;
            return $retval;
        }

        if ($newrev = process_new_icon($context, 'user', 'icon', 0, $fullpath)) {
            $DB->set_field('user', 'picture', $newrev, ['id' => $uid]);
            $retval['success'] = true;
        } else {
            $retval['success'] = false;
        }

        $storedfile->delete();

        \core\event\user_updated::create_from_userid($uid)->trigger();
        return $retval;
    }

    /**
     *
     * External function return definition.
     *
     * @return external_single_structure
     */
    public static function save_profile_picture_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Picture saved and profile updated?'),
        ]);
    }

}

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
 * Strings for the quizaccess_videocapture plugin.
 *
 * @package    quizaccess_videocapture
 * @copyright  2022 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$string['abacoprivacy'] = 'During the face recognition process the following steps are performed by the plugin:<ul>
<li>A picture of the user in front of the screen is taken with the device webcam;</li>
<li>The picture, together with the saved profile picture of the logged in user, is sent to the Abaco Technology API to be checked;</li>
<li>No personal data is sent with the two images;</li>
<li>The Abaco Technology API checks whether the faces in the two images match, returning true/false;</li>
<li>After performing the check both images are deleted;</li>
<li>No personal data is saved during the whole process.</li>
</ul>';
$string['abacoprivacyextlink'] = 'Abaco Technology privacy policy';
$string['abacoprivacytitle'] = 'Face recognition privacy notice';
$string['api_password'] = 'Biometric API password';
$string['api_password_desc'] = 'Biometric API password';
$string['api_url'] = 'Biometrics API url';
$string['api_url_desc'] = 'Biometrics API url';
$string['api_user'] = 'Biometrics API username';
$string['api_user_desc'] = 'Biometrics API username';
$string['bioerr000'] = 'Could not connect to the biometrics system';
$string['bioerr001'] = 'This is not a valid ID card';
$string['bioerr002'] = 'In the picture there must be one face';
$string['bioerr003'] = 'We could not detect a face in the picture';
$string['biogenerr'] = 'The choosen file is invalid';
$string['captured_pic'] = 'New profile picture';
$string['checkduringquiz'] = 'Enable face recognition in the attempt page';
$string['checkinterval'] = 'Control interval (secs)';
$string['continuetoquiz'] = 'You can now access the quiz by clicking the button below.';
$string['facevideocaptureenabled'] = 'Face recognition';
$string['facevideocaptureenabled_help'] = 'If checked the camera will be used to match the student face against the profile picture.';
$string['loginfailed'] = 'Login failed: the captured image does not match the new profile picture. Please stay in front of the camera and try again.';
$string['maxfailedchecks'] = 'Max number of allowed consecutive failed cheks during attempt';
$string['noimage00optionshoot'] = 'I want to take a new picture now with the camera.';
$string['noimage00optionupload'] = 'I want to upload an existing picture.';
$string['noimage00title'] = 'Would you like to upload an existing picture or take a new picture now with the camera?';
$string['noimage01title'] = 'Please stay in front of the camera and click the <em>Take picture</em> button.';
$string['noimage01uploadtitle'] = 'Please upload a picture.';
$string['pictureacquisition'] = 'Profile picture acquisition';
$string['pluginname'] = 'Face recognition quiz access rule';
$string['privacy:metadata'] = 'Face recognition quiz access rule plugin does not store any personal data.';
$string['recogloader'] = 'Please wait, recognition is under way ...';
$string['recogloaderaccessonly'] = 'Please wait, recognition is underway ...';
$string['recogloadercheck'] = 'Please wait, picture check is underway ...';
$string['recogloadersave'] = 'Please wait, picture is being saved and access is underway ...';
$string['recogrequired'] = 'This quiz requires user\'s face recognition. Please click the button to start the camera and complete the process.';
$string['recogrequirednoimage'] = 'This quiz requires users\'s face recognition, but you don\'t have any profile picture yet.<br>To start the acquisition process, please click the <em>Start acquisition</em> button.';
$string['rek_api_error'] = 'Error connecting to biometric API';
$string['rek_exit_quiz'] = 'Exit quiz';
$string['rek_modal_captured_pic'] = 'Captured image';
$string['rek_modal_message'] = 'The captured image does not match the user profile picture. Please stay in front of the camera and try again by clicking the <em>Start recognition</em> button.';
$string['rek_modal_message_attempt'] = 'The captured image does not match the user profile picture. Please stay in front of the camera and try again by clicking the <em>Try again</em> button.';
$string['rek_modal_message_locked'] = 'You have reached the maximum number of failed recognitions. You have to exit the quiz.';
$string['rek_modal_message_success'] = 'You have been successfully recognized. You can now start your quiz attempt using the button below.';
$string['rek_modal_title'] = 'User recognition';
$string['rek_modal_user_pic'] = 'User profile picture';
$string['rek_try_again'] = 'Try again';
$string['saveandlogin'] = 'Save and login';
$string['saveandloginhelp'] = 'You can now finish the process by clicking the <em>Save and login</em> or upload another picture by clicking the <em>Upload another picture</em> button .';
$string['saveandloginshoothelp'] = 'You can now finish the process by clicking the <em>Save and login</em> or take another picture by clicking the <em>Take picture</em> button.';
$string['settingfromidonly'] = 'Allow ID picture only';
$string['settingrelay'] = 'Use relay';
$string['settingrelay_desc'] = 'Use relay';
$string['settingstrongdetection'] = 'Enable strong face detection on quiz entrance';
$string['startacquisition'] = 'Start acquisition';
$string['startvideocap'] = 'Start recognition';
$string['takepicture'] = 'Take picture';
$string['uploadanotherpic'] = 'Upload another picture.';
$string['videocaptureintro'] = 'Facial recognition is enabled for this quiz. Student\'s face must match the profile picture';

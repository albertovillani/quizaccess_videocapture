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
 * Implementaton of the quizaccess_videocapture plugin.
 *
 * @package    quizaccess_videocapture
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
require_once($CFG->libdir . '/outputcomponents.php');


/**
 * If the videocapture flag is checked in the quiz properties, the student face will be compared to the profile picture
 *
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_videocapture extends quiz_access_rule_base {

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
		global $DB;
		
		$videocapture_enabled = $DB->get_record('quizaccess_videocap_settings', array("quizid" => $quizobj->get_quiz()->id));
		
        if ($videocapture_enabled->videocapture == 1) {
            return new self($quizobj, $timenow);
        }

        return null;
    }
	
	public function is_preflight_check_required($attemptid) {
        // Warning only required if the attempt is not already started.
        return ($attemptid === null && !is_siteadmin());
    }
	
	public function add_preflight_check_form_fields(mod_quiz_preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {
		global $PAGE, $COURSE, $USER, $DB;
		
		$context = \context_user::instance($USER->id);
		$no_image = false;
		
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
			$no_image = true;
		}

		if($no_image){
			$html = html_writer::start_tag('div', ['class' =>'extcontainer']);
			$mform->addElement('html', $html);
			$html = html_writer::tag('span', get_string('recogrequirednoimage','quizaccess_videocapture'), array('class'=>'preflighttitle'));
		}else{
			$html = html_writer::start_tag('div', ['class' =>'extcontainer short']);
			$mform->addElement('html', $html);
			$html = html_writer::tag('span', get_string('recogrequired','quizaccess_videocapture'), array('class'=>'preflighttitle'));
		}
		$mform->addElement('html', $html);
		
		if($no_image){
			$mform->addElement('button', 'startvideocap', get_string('startacquisition', 'quizaccess_videocapture'));
		}else{
			$mform->addElement('button', 'startvideocap', get_string('startvideocap', 'quizaccess_videocapture'));
		}

		$output = $PAGE->get_renderer('quizaccess_videocapture');
		$html = $output->preflight_form_html($context->id, $no_image);
		
		if($no_image){
			$user_picture = new user_picture($USER);
			$user_picture->size = 1;
			$user_picurl = $user_picture->get_url($PAGE);

			$PAGE->requires->js_call_amd('quizaccess_videocapture/face_recognition_no_image', 'init', [$USER->id, $context->id, $user_picurl->out()]);
		}else{
			$PAGE->requires->js_call_amd('quizaccess_videocapture/face_recognition', 'init');
		}

		$mform->addElement('html', $html);
		$mform->addElement('hidden', 'facematched', '0');
		$mform->disabledif('submitbutton', 'facematched', 'eq', 0);

		//if($no_image){
			$html = html_writer::end_tag('div');
			$mform->addElement('html', $html);
		//}
		
    }
	
	public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
			$mform->addElement('advcheckbox', 'videocapture', get_string('facevideocaptureenabled', 'quizaccess_videocapture'), get_string('enable'), null, array(0, 1));
			$mform->addHelpButton('videocapture', 'facevideocaptureenabled', 'quizaccess_videocapture');
	}
	
	public static function save_settings($quiz) {
        global $DB;
		
		$videocaptureobj = new stdClass();
		$videocaptureobj->quizid = $quiz->id;
		$videocaptureobj->videocapture = $quiz->videocapture;
		
		$quizrecord = $DB->get_record('quizaccess_videocap_settings', array("quizid" => $quiz->id));
		if($quizrecord){
			$videocaptureobj->id = $quizrecord->id;
			$DB->update_record('quizaccess_videocap_settings', $videocaptureobj);
		}else{
			$DB->insert_record('quizaccess_videocap_settings', $videocaptureobj);
		}
    }
	
	public static function get_settings_sql($quizid) {
        return array('vdc.videocapture', 'LEFT JOIN {quizaccess_videocap_settings} vdc ON vdc.quizid = quiz.id', array());
    }

    public function description() {
        return get_string('videocaptureintro', 'quizaccess_videocapture', $this->quiz->attempts);
    }
	
    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule. securewindow rule is an example of where
     * this is used.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
		global $USER, $COURSE;
		
		$config = get_config('quizaccess_videocapture');
	
		if(!$config->checkduringquiz || strpos($page->url,'attempt.php') === false){
			return;
		}
		$bc = new block_contents();
        $bc->attributes['id'] = 'mod_quiz_navblock';
        $bc->attributes['role'] = 'navigation';
        $bc->title = get_string('facevideocaptureenabled', 'quizaccess_videocapture');
		
		$output = $page->get_renderer('quizaccess_videocapture');
        $bc->content = $output->videocapture_fake_block();
		
		$regions = $page->blocks->get_regions();
		$page->blocks->add_fake_block($bc, reset($regions));
		if(!is_siteadmin()){
			$context = \context_user::instance($USER->id);
			$page->requires->js_call_amd('quizaccess_videocapture/face_recognition_block', 'init', 
			                              array($context->id, $COURSE->id, $config->checkinterval*1000, $config->maxfailedchecks));
		}
    }	

}

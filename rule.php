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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\form\preflight_check_form;
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;


/**
 * If the videocapture flag is checked in the quiz properties, the student face will be compared to
 * the profile picture
 *
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_videocapture extends access_rule_base {

    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     *
     * @param \mod_quiz\quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return access_rule_base|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $DB;

        $videocaptureenabled = $DB->get_record('quizaccess_videocap_settings', ["quizid" => $quizobj->get_quiz()->id]);

        if ($videocaptureenabled->videocapture == 1) {
            return new self($quizobj, $timenow);
        }

        return null;
    }

    /**
     * Is preflight check required?
     *
     * @param int $attemptid the id of the attempt.
     * @return bool required / not required
     */
    public function is_preflight_check_required($attemptid) {
        // Warning only required if the attempt is not already started.
        return ($attemptid === null && !is_siteadmin());
    }

    /**
     * Function for adding fields to the preflight check form
     *
     * @param mod_quiz_preflight_check_form $quizform quiz preflight check form instance.
     */
    public function add_preflight_check_form_fields(preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {
        global $PAGE, $USER, $DB;

        $context = \context_user::instance($USER->id);
        $noimage = false;

        $filetype = 'image/png';
        if (!$filerecord = $DB->get_record('files', ['contextid' => $context->id, 'component' => 'user', 'filearea' => 'icon',
                                                     'filepath' => '/', 'filename' => 'f1.png'])) {
            $filetype = 'image/jpg';
            $filerecord = $DB->get_record('files', ['contextid' => $context->id, 'component' => 'user', 'filearea' => 'icon',
                                                    'filepath' => '/', 'filename' => 'f1.jpg']);
        }
        $fs = get_file_storage();
        $fileinfo = ['component' => 'user',
                     'filearea' => 'icon',
                     'itemid' => 0,
                     'contextid' => $context->id,
                     'filepath' => '/',
                     'filename' => $filerecord->filename];

        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
            $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

        if (!$file) {
            $noimage = true;
        }

        if ($noimage) {
            $html = html_writer::start_tag('div', ['class' => 'extcontainer']);
            $mform->addElement('html', $html);
            $html = html_writer::tag('span', get_string('recogrequirednoimage', 'quizaccess_videocapture'),
                                      ['class' => 'preflighttitle']);
        } else {
            $html = html_writer::start_tag('div', ['class' => 'extcontainer short']);
            $mform->addElement('html', $html);
            $html = html_writer::tag('span', get_string('recogrequired', 'quizaccess_videocapture'),
                                     ['class' => 'preflighttitle']);
        }
        $mform->addElement('html', $html);

        if ($noimage) {
            $mform->addElement('button', 'startvideocap', get_string('startacquisition', 'quizaccess_videocapture'));
        } else {
            $mform->addElement('button', 'startvideocap', get_string('startvideocap', 'quizaccess_videocapture'));
        }

        $output = $PAGE->get_renderer('quizaccess_videocapture');
        $html = $output->preflight_form_html($context->id, $noimage);

        if ($noimage) {
            $userpicture = new user_picture($USER);
            $userpicture->size = 1;
            $userpicurl = $userpicture->get_url($PAGE);

            $PAGE->requires->js_call_amd('quizaccess_videocapture/face_recognition_no_image', 'init',
                                         [$USER->id, $context->id, $userpicurl->out()]);
        } else {
            $PAGE->requires->js_call_amd('quizaccess_videocapture/face_recognition', 'init');
        }

        $mform->addElement('html', $html);
        $mform->addElement('hidden', 'facematched', '0');
        $mform->disabledif('submitbutton', 'facematched', 'eq', 0);

        $html = html_writer::end_tag('div');
        $mform->addElement('html', $html);

        $html = html_writer::start_tag('div', ['class' => 'abacofooter']);
        $html .= html_writer::start_tag('div', ['class' => 'abacoprivacy']);
        $html .= html_writer::tag('a', get_string('abacoprivacytitle', 'quizaccess_videocapture'),
                                  ['id' => 'abacoprivacylink', 'href' => '#']);
        $html .= html_writer::end_tag('div');
        $html .= html_writer::div('Powered by', 'abacobrand');
        $html .= html_writer::end_tag('div');
        $mform->addElement('html', $html);
    }

    /**
     * Function for adding fields to the settings form
     *
     * @param mod_quiz_mod_form $quizform quiz settings form instance.
     * @param MoodleQuickForm $mform quiz settings form instance.
     */
    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
            $mform->addElement('advcheckbox', 'videocapture',
                                get_string('facevideocaptureenabled', 'quizaccess_videocapture'),
                                get_string('enable'), null, [0, 1]);
            $mform->addHelpButton('videocapture', 'facevideocaptureenabled', 'quizaccess_videocapture');
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from {@link quiz_after_add_or_update()} in lib.php.
     *
     * @param stdClass $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz) {
        global $DB;

        $videocaptureobj = new stdClass();
        $videocaptureobj->quizid = $quiz->id;
        $videocaptureobj->videocapture = $quiz->videocapture;

        $quizrecord = $DB->get_record('quizaccess_videocap_settings', ["quizid" => $quiz->id]);
        if ($quizrecord) {
            $videocaptureobj->id = $quizrecord->id;
            $DB->update_record('quizaccess_videocap_settings', $videocaptureobj);
        } else {
            $DB->insert_record('quizaccess_videocap_settings', $videocaptureobj);
        }
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) {
        return ['vdc.videocapture', 'LEFT JOIN {quizaccess_videocap_settings} vdc ON vdc.quizid = quiz.id', []];
    }

    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     *
     * @return mixed a message, or array of messages, explaining the restriction
     *         (may be '' if no message is appropriate).
     */
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

        if (!$config->checkduringquiz || strpos($page->url, 'attempt.php') === false) {
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
        if (!is_siteadmin()) {
            $moodleurl = new moodle_url('/').'';
            $context = \context_user::instance($USER->id);
            $page->requires->js_call_amd('quizaccess_videocapture/face_recognition_block', 'init',
                                          [$context->id, $COURSE->id, $config->checkinterval * 1000,
                                          $config->maxfailedchecks, $moodleurl]);
        }
    }
}

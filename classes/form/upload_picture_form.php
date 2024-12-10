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
 * quizaccess_videocapture upload picture form
 *
 * @package     quizaccess_videocapture
 * @copyright   2023 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_videocapture\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * quizaccess_videocapture upload picture form
 *
 * @package     quizaccess_videocapture
 * @copyright   2023 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_picture_form extends \moodleform {


    /**
     *
     * Add elements to form.
     *
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement(
            'filepicker',
            'newpicture',
            get_string('file'),
            null,
            [
                'maxbytes' => 2000000,
                'accepted_types' => ['.png', '.jpg'],
            ]
        );

    }

    /**
     *
     * Validation.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     */
    public function validation($data, $files) {
        return [];
    }


}

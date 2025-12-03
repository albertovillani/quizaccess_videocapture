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
 * Renderer outputting the quizaccess_videocapture preflight form.
 *
 * @package    quizaccess_videocapture
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_videocapture\output;

use html_writer;

/**
 * Renderer outputting the quizaccess_videocapture preflight form.
 *
 * @package    quizaccess_videocapture
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */
class renderer extends \plugin_renderer_base {


    /**
     * Display the preflight_form_html.
     *
     * @param int $usercontext User's context id
     * @param bool $noimage Has the user an associated profile picture? true/false
     * @param bool $audiomonitorenabled Whether microphone monitoring UI should be shown.
     * @return string HTML to output.
     */
    public function preflight_form_html($usercontext, $noimage, $audiomonitorenabled = true) {
        $data = new \stdClass();
        $data->user_context = $usercontext;
        $data->getparam = time();
        $data->moodle_url = new \moodle_url("/");
        $data->audiomonitor_enabled = $audiomonitorenabled;

        // Render the preflight form.
        $output = '';
        if ($noimage) {
            $output .= $this->render_from_template('quizaccess_videocapture/preflight_form_no_image_00', [
                'audiomonitor_enabled' => $audiomonitorenabled,
            ]);
        } else {
            $output .= $this->render_from_template('quizaccess_videocapture/preflight_form', $data);
        }
        return $output;
    }


    /**
     * Returns the videcapture fake block content for the attempt pages.
     * @param bool $facechecks Whether the face-recognition UI should be shown.
     * @param bool $audiomonitorenabled Whether microphone monitoring UI should be shown.
     * @return string HTML to output.
     */
    public function videocapture_fake_block($facechecks = true, $audiomonitorenabled = true) {
        $content = html_writer::start_tag('div', ['class' => 'othernav']);
        if ($audiomonitorenabled) {
            $content .= html_writer::start_tag('div', [
                'id' => 'audio-monitor-attempt',
                'class' => 'audio-monitor status-idle',
                'role' => 'status',
                'aria-live' => 'polite',
                'data-prompt' => \get_string('audiomonitorprompt', 'quizaccess_videocapture'),
                'data-ready' => \get_string('audiomonitorready', 'quizaccess_videocapture'),
                'data-fallback' => \get_string('audiomonitorfallback', 'quizaccess_videocapture'),
                'data-error' => \get_string('audiomonitorerror', 'quizaccess_videocapture'),
                'data-speaking' => \get_string('audiomonitoractivity', 'quizaccess_videocapture'),
                'data-silence' => \get_string('audiomonitorsilence', 'quizaccess_videocapture'),
            ]);
            $content .= html_writer::span(\get_string('audiomonitorheading', 'quizaccess_videocapture'),
                                          'audio-monitor__label');
            $content .= html_writer::tag('span', '', ['class' => 'audio-monitor__icon', 'aria-hidden' => 'true']);
            $content .= html_writer::span(\get_string('audiomonitorprompt', 'quizaccess_videocapture'),
                                          'audio-monitor__text');
            $content .= html_writer::end_tag('div');
        }
        if ($facechecks) {
            $content .= html_writer::tag('img', '', ['id' => 'photo', 'style' => 'display:none']);
            $content .= html_writer::tag('video', '', ['id' => 'video']);
            $content .= html_writer::start_tag('canvas', ['id' => 'canvas', 'style' => 'display:none',
                                                    'width' => '210px', 'height' => '157px']);
            $content .= html_writer::end_tag('canvas');
        }
        $content .= html_writer::end_tag('div');
        return $content;
    }

}

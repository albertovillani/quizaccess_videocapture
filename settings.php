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
 * Settings for quizaccess_videocapture plugin.
 *
 * @package    quizaccess_videocapture
 * @copyright  2023 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext('quizaccess_videocapture/api_user',
        get_string('api_user', 'quizaccess_videocapture'), get_string('api_user_desc', 'quizaccess_videocapture'), ''));

    $settings->add(new admin_setting_configtext('quizaccess_videocapture/api_password',
        get_string('api_password', 'quizaccess_videocapture'), get_string('api_password_desc', 'quizaccess_videocapture'), ''));

    $settings->add(new admin_setting_configtext('quizaccess_videocapture/api_url',
                   get_string('api_url', 'quizaccess_videocapture'), get_string('api_url_desc', 'quizaccess_videocapture'),
                   'https://biometrics.abacotechnology.com/api'));

    $settings->add(new admin_setting_configcheckbox('quizaccess_videocapture/fromidonly',
                   get_string('settingfromidonly', 'quizaccess_videocapture'),
                   get_string('settingfromidonly', 'quizaccess_videocapture'), ''));

    $settings->add(new admin_setting_configcheckbox('quizaccess_videocapture/checkduringquiz',
        get_string('checkduringquiz', 'quizaccess_videocapture'), get_string('checkduringquiz', 'quizaccess_videocapture'), ''));

    $intervals = [];
    $intervals[5] = 5;
    $intervals[10] = 10;
    $intervals[15] = 15;
    $intervals[20] = 20;
    $intervals[25] = 25;
    $intervals[30] = 30;
    $intervals[35] = 35;
    $intervals[40] = 40;
    $intervals[45] = 45;
    $intervals[50] = 50;
    $intervals[55] = 55;
    $intervals[60] = 60;

    $settings->add(new admin_setting_configselect('quizaccess_videocapture/checkinterval',
                   get_string('checkinterval', 'quizaccess_videocapture'),
                   get_string('checkinterval', 'quizaccess_videocapture'), 5, $intervals));

    $maxfailedchecks = [];
    for ($i = 1; $i < 11; $i++) {
        $maxfailedchecks[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('quizaccess_videocapture/maxfailedchecks',
        get_string('maxfailedchecks', 'quizaccess_videocapture'), get_string('maxfailedchecks', 'quizaccess_videocapture'), 3,
        $maxfailedchecks));
}

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
 * Web service external functions and service definitions.
 *
 * @package   quizaccess_videocapture
 * @copyright 2022 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// We defined the web service functions to install.
$functions = [
    'quizaccess_videocapture_check_snapshot' => [
        'classname'     => 'quizaccess_videocapture\external\videocapture_external',
        'methodname'    => 'check_snapshot',
        'description'   => 'Check the snapshot of the user against his profile picture.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => false,
    ],
    'quizaccess_videocapture_check_profile_picture' => [
        'classname'     => 'quizaccess_videocapture\external\videocapture_external',
        'methodname'    => 'check_profile_picture',
        'description'   => 'Check the ID document image shot by the user and crop the face image.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => false,
    ],
    'quizaccess_videocapture_check_uploaded_profile_picture' => [
        'classname'     => 'quizaccess_videocapture\external\videocapture_external',
        'methodname'    => 'check_uploaded_profile_picture',
        'description'   => 'Check the ID document image uploaded by the user and crop the face image.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => false,
    ],
    'quizaccess_videocapture_save_profile_picture' => [
        'classname'     => 'quizaccess_videocapture\external\videocapture_external',
        'methodname'    => 'save_profile_picture',
        'description'   => 'Save the new profile picture for the user.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => false,
    ],
];

$services = [
      'quizrulevideocaptureservice' => [
          'functions' => ['quizaccess_videocapture_check_snapshot',
                                'quizaccess_videocapture_check_profile_picture',
                                'quizaccess_videocapture_save_profile_picture',
                                'quizaccess_videocapture_check_uploaded_profile_picture'],
          'restrictedusers' => 0,
          'enabled' => 1,
          'shortname' => 'quizrulevideocaptureservice',
          'downloadfiles' => 0,
          'uploadfiles'  => 0,
       ],
  ];

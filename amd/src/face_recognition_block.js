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
 * Manage the face recognition process in the quiz attempt block.
 *
 * @module     quizaccess_videocapture/face_recognition_block
 * @author     Alberto Villani <alberto.villani@abacotechnology.it>
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Templates from 'core/templates';
import ModalFaceCheck from 'quizaccess_videocapture/modal_facecheck';
import RC from 'quizaccess_videocapture/face_recognition_client';

var user_context = null;
var course = null;
var check_interval = null;
var max_failed_checks = null;
var moodleurl = null;

export const init = (user_context_id, course_id, interval, maxfailedchecks, moodle_url) => {

    user_context = user_context_id;
    course = course_id;
    check_interval = interval;
    max_failed_checks = maxfailedchecks;
    moodleurl = moodle_url;

    RC.setFlow('attempt');
    RC.setPhotoTarget('photo_target');
    RC.startVideoCap('video', 'canvas', 'photo');


    document.addEventListener('snapshotCheckSuccess', () =>{
         window.setTimeout(RC.takepicture, check_interval);
    });

    document.addEventListener('snapshotCheckFailed', (e) =>{
        modalFunction(e.detail.failed_attempt, e.detail.remote_error);
    });
};

const modalFunction = async(fa, remote_error) => {
    let lock = (fa >= max_failed_checks)? true : false;
    let body = '';
    if(remote_error){
        body = 'quizaccess_videocapture/modal_facecheck_body_api_error';
    }else{
        body = 'quizaccess_videocapture/modal_facecheck_body';
    }
    const modal = await ModalFaceCheck.create({
                    body: Templates.render(body,
                          {captured_pic: $('#photo').attr('src'), user_context: user_context, lock: lock,
                          nocachecode: Date.now(), moodleurl: moodleurl}
                  )
    });
    modal.setRecClient(RC);
    modal.setExitLink(moodleurl+'course/view.php?id='+course);
    if(fa >= max_failed_checks){
        modal.hideTryAgain();
    }
    modal.show();
};
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
 * Manage the face recognition process.
 *
 * @module     quizaccess_videocapture/face_recognition
 * @author     Alberto Villani <alberto.villani@abacotechnology.it>
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import ModalCancel from 'core/modal_cancel';
import RC from 'quizaccess_videocapture/face_recognition_client';
import Templates from 'core/templates';
import {getString} from 'core/str';

export const init = () => {

    $('input[name="cancel"]').on('click', () => {
        $('#recogfailed').hide();
        $('#recogsuccess').hide();
        $('#recogloader').hide();
        $('.videocappreflight').hide();
        $('input[name="submitbutton"]').attr('disabled', 'disabled');
        RC.releaseStream();
    });

    $('button[name="startvideocap"]').on('click', function(){
        $('#recogfailed').hide();
        $('.pic_container').show();
        $('#failedmessage').show();
        $('#failedmessage_api').hide();
        $('#recogsuccess').hide();
        $('#recogloader').hide();
        $('.videocappreflight').show();
        if(RC.stream === null){
            RC.startVideoCap('video', 'canvas', 'photo');
        }else{
            $('#recogloader').show();
            RC.takepicture();
        }
    });

    $('#abacoprivacylink').on('click', function (e){
        e.preventDefault();
        privacyModalFunction();
    });

    document.addEventListener('snapshotCheckFailed', (e) =>{
        $('input[name="facematched"]').attr('value', '0');
        $('input[name="submitbutton"]').attr('disabled', 'disabled');
        $('#recogsuccess').hide();
        $('#recogloader').hide();
        $('#recogfailed').show();
        if(e.detail.remote_error){
            $('.pic_container').hide();
            $('#failedmessage').hide();
            $('#failedmessage_api').show();
        }
    });

    document.addEventListener('snapshotCheckSuccess', () =>{
        $('input[name="facematched"]').attr('value', '1');
        $('input[name="submitbutton"]').removeAttr('disabled');
        $('#recogfailed').hide();
        $('#recogloader').hide();
        $('#recogsuccess').show();
    });

    document.getElementById('video').addEventListener('canplay', () => {
        $('#recogloader').show();
    });
};

const privacyModalFunction = async() => {
    const privacyModal = await ModalCancel.create({
        title: getString('abacoprivacytitle','quizaccess_videocapture'),
        body: Templates.render('quizaccess_videocapture/modal_privacy_body'),
    });
    privacyModal.show();
};

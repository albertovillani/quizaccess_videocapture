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
import AudioMonitor from 'quizaccess_videocapture/audio_monitor';

const submitsel = 'input[name="submitbutton"]';
const facesel = 'input[name="facematched"]';
const audiosel = 'input[name="audiocheck"]';

const isAudioAllowed = () => {
    const $audio = $(audiosel);
    if (!$audio.length) {
        return true;
    }
    return $audio.val() === '1';
};

const syncSubmitButton = () => {
    const $submit = $(submitsel);
    if (!$submit.length) {
        return;
    }
    const faceMatched = $(facesel).val() === '1';
    if (faceMatched && isAudioAllowed()) {
        $submit.removeAttr('disabled');
    } else {
        $submit.attr('disabled', 'disabled');
    }
};

const setFacematched = (matched) => {
    const $face = $(facesel);
    if (!$face.length) {
        return;
    }
    const target = matched ? '1' : '0';
    if ($face.val() !== target) {
        $face.val(target);
    }
    $face.trigger('change');
    syncSubmitButton();
};

export const init = () => {

    AudioMonitor.init({
        containerSelector: '#audio-monitor',
        hiddenInputSelector: 'input[name="audiocheck"]',
        submitButtonSelector: 'input[name="submitbutton"]',
        facematchedSelector: 'input[name="facematched"]'
    });
    $(audiosel).on('change.vcSubmitGuard', () => {
        syncSubmitButton();
    });
    $(facesel).on('change.vcSubmitGuard', () => {
        syncSubmitButton();
    });
    syncSubmitButton();

    $('input[name="cancel"]').on('click', () => {
        $('#recogfailed').hide();
        $('#recogsuccess').hide();
        $('#recogloader').hide();
        $('.videocappreflight').hide();
        setFacematched(false);
        RC.releaseStream();
        AudioMonitor.stop();
    });

    $('button[name="startvideocap"]').on('click', function(){
        $('#recogfailed').hide();
        $('.pic_container').show();
        $('#failedmessage').show();
        $('#failedmessage_api').hide();
        $('#recogsuccess').hide();
        $('#recogloader').hide();
        $('.videocappreflight').show();
        setFacematched(false);
        AudioMonitor.start().catch(() => {
            // UI will surface the detailed error state via the monitor panel.
        });
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
        setFacematched(false);
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
        setFacematched(true);
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

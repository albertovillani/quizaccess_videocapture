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
 * Manage the first step of the acquisition of a new profile picture.
 *
 * @module     quizaccess_videocapture/face_recognition_no_image
 * @author     Alberto Villani <alberto.villani@abacotechnology.it>
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import ModalCancel from 'core/modal_cancel';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';

export const init = (userid, usercontextid, user_picurl) => {

    $('button[name="startvideocap"]').on('click', function(){
        $('.preflighttitle').hide();
        $('#fitem_id_startvideocap').hide();
        $('.videocappreflight #container00').show();
        $('.videocappreflight').show();
    });

    $('.continue00').on('click', function(e){
        e.preventDefault();
        const context = {
            name: 'videocappreflight01',
            userid: userid,
            usercontextid: usercontextid,
            user_picurl: user_picurl
        };
        $('.videocappreflight #container01').remove();
        let tmpl = '';
        switch($('input[name="upload"]:checked').val()){
            case "0":
                tmpl = 'quizaccess_videocapture/preflight_form_no_image_01_shoot';
                break;
            case "1":
                tmpl = 'quizaccess_videocapture/preflight_form_no_image_01_upload';
                break;
        }
        if(tmpl != ''){
            Templates.renderForPromise(tmpl, context)
            .then((html) => {
                $('#container00').hide();
                Templates.appendNodeContents('.videocappreflight', html.html, html.js);
            }).catch(ex => Notification.exception(ex));
        }

    });

    $('#abacoprivacylink').on('click', function (e){
        e.preventDefault();
        privacyModalFunction();
    });
};

const privacyModalFunction = async() => {
    const privacyModal = await ModalCancel.create({
        title: getString('abacoprivacytitle','quizaccess_videocapture'),
        body: Templates.render('quizaccess_videocapture/modal_privacy_body'),
    });
    privacyModal.show();
};

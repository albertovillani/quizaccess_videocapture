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
 * Manage the acquisition of a new profile picture via the webcam.
 *
 * @module     quizaccess_videocapture/face_recognition_no_image_01_shoot
 * @author     Alberto Villani <alberto.villani@abacotechnology.it>
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/templates',
'core/ajax', 'quizaccess_videocapture/face_recognition_client'],
 function($, ModalFactory, ModalEvents, Templates, ajax, RC) {

    var uid = null;

    return{
        init: function(userid){

            uid = userid;
            RC.setFlow('shoot');
            RC.setUid(uid);
            RC.setPhotoTarget('photo_target');

            document.getElementById('video').addEventListener('canplay', function(){
                $('#recogloader').show();
                $('.shot_container').show();
                $('button[name="takepicture"]').show();
            });

            RC.startVideoCap('video', 'canvas', 'photo');


            $('button[name="takepicture"]').on('click', function(e){
                e.preventDefault();
                RC.setStep(0);
                $('#capturedpicture').show();
                $('.icon_container').show();
                $('#capturedmessage').show();
                $('.errmsg').hide();
                $('#capturederror').hide();
                $('.shot_container').hide();
                $('#recogfailed').hide();
                $('#recogsuccess').hide();
                $('button[name="saveandlogin"]').hide();
                 $('#slhelp').hide();
                RC.takepicture();
            });

            $('button[name="saveandlogin"]').on('click', function(e){
                e.preventDefault();
                $('#recogfailed').hide();
                $('#svl-bio-err').show();
                $('#svl-api-error').hide();
                $('#recogsuccess').hide();
                $('#slhelp').hide();
                $('#videocheck').show();
                $('#recogloader').show();
                RC.saveandlogin();
            });

            $('button[name="backbtn"]').on('click', function(e){
                e.preventDefault();
                RC.releaseStream();
                $('.videocappreflight #container01').remove();
                $('#container00').show();

            });

            $('input[name="cancel"]').on('click', () => {
                $('.videocappreflight #container01').remove();
                $('.preflighttitle').show();
                $('#fitem_id_startvideocap').show();
                $('.videocappreflight').hide();
                $('input[name="submitbutton"]').attr('disabled', 'disabled');
                RC.releaseStream();
            });

            document.addEventListener('newpictureCheckSuccess', () =>{
                RC.setStep(1);
                $('.icon_container').hide();
                $('.shot_container').show();
                $('#photo_target').show();
                $('#slhelp').show();
                $('button[name="saveandlogin"]').show();
            });

            document.addEventListener('newpictureCheckFailed', (e) =>{
                RC.setStep(0);
                $('#capturedmessage').hide();
                if(e.detail.remote_error){
                    $('#errormsg-BIO-API-ERR').show();
                }else{
                    $('#errormsg-'+e.detail.msgcod).show();
                }
                $('#capturederror').show();
            });

            document.addEventListener('saveandloginCheckSuccess', () =>{
                $('#recogfailed').hide();
                $('#recogloader').hide();
                $('.actionbtn').attr('disabled', true);
                $('input[name="facematched"]').attr('value', '1');
                $('input[name="submitbutton"]').removeAttr('disabled');
                $('#recogsuccess').show();

            });

            document.addEventListener('saveandloginCheckFailed', (e) =>{
                $('#recogsuccess').hide();
                $('#recogloader').hide();
                if(e.detail.remote_error){
                    $('#svl-bio-err').hide();
                    $('#svl-api-error').show();
                }
                $('#recogfailed').show();
            });

        }
    };

});
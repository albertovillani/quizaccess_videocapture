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
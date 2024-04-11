define(['jquery', 'core/templates', 'core/ajax', 'core/notification',
'core/fragment', 'quizaccess_videocapture/face_recognition_client'],
 function($, Templates, ajax, Notification, Fragment, RC) {

    //var uid = null;
    //var usercontextid = null;


    var loadForm = function(userid, usercontextid){
        $('#formcontainer form').remove();
        Fragment.loadFragment('quizaccess_videocapture', 'uploadform',usercontextid)
        .then((frgmnt_html, frgmnt_js) =>{
            Templates.appendNodeContents('#formcontainer', frgmnt_html, frgmnt_js);
            $('.videocappreflight').css('height', '440px');
            $('#sendpicture').off('click');
            $('#sendpicture').on('click', (e) => {
                e.preventDefault();
                if($('input[name="newpicture"]').attr('value') == ''){
                    return;
                }
                $('.sectionnavbar').hide();
                $('button[name="backbtn2"]').hide();
                var formData = $('#formcontainer form').serialize();

                $('#formcontainer').hide();
                $('#capturederror').hide();
                $('.shot_container').hide();
                $('#upldretry').hide();
                $('#saveandlogin').hide();
                $('.errmsg').hide();

                $('input[name="submitbutton"]').attr('disabled', 'disabled');
                $('#fgroup_id_buttonar').show();
                $('#capturedpicture').show();
                $('.icon_container').show();
                $('#capturedmessage').show();
                RC.checkUploadedPicture(formData);
            });
            $('.sectionnavbar').show();
        });
    };


    return{
        init: function(userid, usercontextid){

            //let uid = userid;
            RC.setFlow('upload');
            RC.setUid(userid);
            RC.setPhotoTarget('photo_target');

            loadForm(userid, usercontextid);
            $('#fgroup_id_buttonar').hide();

            $('#upldretry').on('click', () => {
                RC.releaseStream();
                $('#videocheck').hide();
                $('#recogfailed').hide();
                $('#capturedpicture').hide();

                $('#formcontainer').hmtl = '';
                $('#formcontainer').show();
                loadForm(userid, usercontextid);
            });

            $('.bckbtn').on('click', function(){
                RC.releaseStream();
                $('.videocappreflight #container01').remove();
                $('#container00').show();
            });

            $('button[name="saveandlogin"]').on('click', function(e){
                e.preventDefault();
                $('#recogfailed').hide();
                $('#svl-bio-err').show();
                $('#svl-api-error').hide();
                $('#videocheck').show();
                $('#recogfailed').hide();
                $('#recogsuccess').hide();
                RC.startVideoCap('video', 'canvas', 'photo');
            });

            $('input[name="cancel"]').on('click', () => {
                $('.videocappreflight #container01').remove();
                $('.preflighttitle').show();
                $('#fitem_id_startvideocap').show();
                $('.videocappreflight').hide();
                $('input[name="submitbutton"]').attr('disabled', 'disabled');
                RC.releaseStream();
            });

            document.addEventListener('uploadedPictureCheckSuccess', () =>{
                $('.icon_container').hide();
                $('.shot_container').show();
                $('#photo_target').show();
                $('#slhelp').show();
                $('#saveandlogin').show();
                $('button[name="backbtn2"]').show();

            });

            document.addEventListener('ploadedPictureCheckFailed', (e) =>{
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
                //$('#photo_target').hide();
                $('.actionbtn').attr('disabled', true);
                $('.pic_label').hide();
                $('button[name="takepicture"]').hide();
                $('input[name="facematched"]').attr('value', '1');
                $('input[name="submitbutton"]').removeAttr('disabled');
                $('#recogloader').hide();
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
                $('button[name="saveandlogin"]').show();
            });

            document.getElementById('video').addEventListener('canplay', function(){
                $('#recogloader').show();
            }, false);
        }
    };
});
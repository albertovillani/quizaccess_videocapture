define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/templates',
'core/ajax', 'quizaccess_videocapture/face_recognition_client'],
 function($, ModalFactory, ModalEvents, Templates, ajax, RC) {

    return{
        init: function(){

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


            $('input[name="cancel"]').on('click', () => {
                $('#recogfailed').hide();
                $('#recogsuccess').hide();
                $('#recogloader').hide();
                $('.videocappreflight').hide();
                $('input[name="submitbutton"]').attr('disabled', 'disabled');
                RC.releaseStream();
            });

            document.addEventListener('snapshotCheckSuccess', () =>{
                $('input[name="facematched"]').attr('value', '1');
                $('input[name="submitbutton"]').removeAttr('disabled');
                $('#recogfailed').hide();
                $('#recogloader').hide();
                $('#recogsuccess').show();
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

            document.getElementById('video').addEventListener('canplay', () => {
                $('#recogloader').show();
            });
        }
    };

});
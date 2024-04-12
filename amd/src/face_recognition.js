define(['jquery', 'quizaccess_videocapture/face_recognition_client'],
 function($, recClient) {

    return{
        init: function(){

            $('input[name="cancel"]').on('click', () => {
                $('#recogfailed').hide();
                $('#recogsuccess').hide();
                $('#recogloader').hide();
                $('.videocappreflight').hide();
                $('input[name="submitbutton"]').attr('disabled', 'disabled');
                recClient.releaseStream();
            });

            $('button[name="startvideocap"]').on('click', function(){
                $('#recogfailed').hide();
                $('.pic_container').show();
                $('#failedmessage').show();
                $('#failedmessage_api').hide();
                $('#recogsuccess').hide();
                $('#recogloader').hide();
                $('.videocappreflight').show();
                if(recClient.stream === null){
                    recClient.startVideoCap('video', 'canvas', 'photo');
                }else{
                    $('#recogloader').show();
                    recClient.takepicture();
                }
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
        }
    };

});
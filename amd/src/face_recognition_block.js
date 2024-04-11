
import $ from 'jquery';
import Templates from 'core/templates';
import ModalFaceCheck from 'quizaccess_videocapture/modal_facecheck';
import RC from 'quizaccess_videocapture/face_recognition_client';

var user_context = null;
var course = null;
var check_interval = null;
var max_failed_checks = null;

export const init = (user_context_id, course_id, interval, maxfailedchecks) => {

    user_context = user_context_id;
    course = course_id;
    check_interval = interval;
    max_failed_checks = maxfailedchecks;

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
    let lock = (fa >= max_failed_checks)?true:false;
    let body = '';
    if(remote_error){
        body = 'quizaccess_videocapture/modal_facecheck_body_api_error';
    }else{
        body = 'quizaccess_videocapture/modal_facecheck_body';
    }
    const modal = await ModalFaceCheck.create({
                    body: Templates.render(body,
                          {captured_pic: $('#photo').attr('src'), user_context: user_context, lock: lock, nocachecode: Date.now()}
                  )
    });
    modal.setRecClient(RC);
    modal.setExitLink('/course/view.php?id='+course);
    if(fa >= max_failed_checks){
        modal.hideTryAgain();
    }
    modal.show();
};
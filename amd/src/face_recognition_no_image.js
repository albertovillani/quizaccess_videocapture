define(['jquery', 'core/templates', 'core/ajax', 'core/notification', 'core/fragment'],
 function($, Templates, ajax, Notification) {

    return{
        init: function(userid, usercontextid, user_picurl){
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
        }
    };
});
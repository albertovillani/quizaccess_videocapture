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
 * Base functions for the face recognition process.
 *
 * @module     quizaccess_videocapture/face_recognition_client
 * @author     Alberto Villani <alberto.villani@abacotechnology.it>
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/templates', 'core/ajax'],
 function($, ModalFactory, ModalEvents, Templates, ajax) {

    var RecognitionClient = function() {

        this.width = 210;
        this.height = 0;
        this.streaming = false;

        this.video = null;
        this.canvas = null;
        this.photo = null;
        this.photoTarget = null;

        this.stream = null;

        this.flow = 'check';
        this.step = 0;
        this.uid = null;

        this.takepicture = this.takepicture.bind(this);
        this.formatDate = this.formatDate.bind(this);
        this.releaseStream = this.releaseStream.bind(this);
        this.clearphoto = this.clearphoto.bind(this);
        this.setFlow = this.setFlow.bind(this);
        this.setPhotoTarget = this.setPhotoTarget.bind(this);
        this.setUid = this.setUid.bind(this);
        this.saveandlogin = this.saveandlogin.bind(this);
        this.checkUploadedPicture = this.checkUploadedPicture.bind(this);
        this.checkPhoto = this.checkPhoto.bind(this);

    };


    RecognitionClient.prototype.setFlow = function(flow){
        this.flow = flow;
    };

    RecognitionClient.prototype.setStep = function(step){
        this.step = step;
    };

    RecognitionClient.prototype.setUid = function(uid){
        this.uid = uid;
    };

    RecognitionClient.prototype.setPhotoTarget = function(photoTargetId){
        this.photoTarget = document.getElementById(photoTargetId);
    };


    RecognitionClient.prototype.startVideoCap = function(videoid, canvasid, photoid){
            this.streaming = false;
            this.video = document.getElementById(videoid);
            this.canvas = document.getElementById(canvasid);
            this.photo = document.getElementById(photoid);

            navigator.getMedia = ( navigator.getUserMedia ||
                                   navigator.webkitGetUserMedia ||
                                   navigator.mozGetUserMedia ||
                                   navigator.msGetUserMedia);

            navigator.getMedia(
                {
                video: true,
                audio: false
                },
                function(stream) {
                    this.stream = stream;
                    if (navigator.mozGetUserMedia) {
                      this.video.mozSrcObject = stream;
                    } else if(navigator.webkitGetUserMedia){
                      this.video.srcObject = stream;
                    } else {
                      var vendorURL = window.URL || window.webkitURL;
                      this.video.src = vendorURL.createObjectURL(stream);
                    }
                    this.video.play();
                }.bind(this),
                function() {
                    //console.log("An error occured! " + err);
                }

            );


            this.video.addEventListener('canplay', function(){
                if (!this.streaming) {
                    let height = this.video.videoHeight / (this.video.videoWidth/this.width);

                    // Firefox currently has a bug where the height can't be read from
                    // the video, so we will make assumptions if this happens.
                    if (isNaN(height)) {
                      height = this.width / (4/3);
                    }

                    this.height = height;

                    this.video.setAttribute('width', this.width);
                    this.video.setAttribute('height', height);
                    this.canvas.setAttribute('width', this.width);
                    this.canvas.setAttribute('height', height);
                    this.streaming = true;
                    if(this.flow == 'check' || this.flow == 'attempt'){
                        window.setTimeout(this.takepicture, 1000);
                    }else if(this.flow == 'upload'){
                        window.setTimeout(this.saveandlogin, 1000);
                    }
                }

            }.bind(this), false);


    };

    RecognitionClient.prototype.releaseStream = function(){
        if(this.stream !== null){
            this.stream.getTracks().forEach(function(track) {
                track.stop();
            });
        }
        this.stream = null;
    };

    // The width and height of the captured photo. We will set the
    // width to the value defined here, but the height will be
    // calculated based on the aspect ratio of the input stream.

    RecognitionClient.prototype.takepicture = function(){

        var context = this.canvas.getContext('2d');

        if (this.width && this.height) {
          this.canvas.width = this.width;
          this.canvas.height = this.height;
          context.drawImage(this.video, 0, 0, this.width, this.height);
          context.font = "10px Arial";
          var date = new Date();
          context.fillText(this.formatDate(date), 20, 20);
          var data = this.canvas.toDataURL('image/png');
          if(this.flow == 'check' || this.flow == 'attempt'){
              this.photo.setAttribute('src', data);
              this.checkPhoto();
          }else if(this.flow == 'shoot'){
              if(this.step === 0){
                  this.photoTarget.setAttribute('src', data);
                  this.checkNewPicture();
              }else{
                  this.photo.setAttribute('src', data);
              }
          }else if(this.flow == 'upload'){
              this.photo.setAttribute('src', data);
          }
        } else {
          this.clearphoto();
        }
    };


    RecognitionClient.prototype.checkPhoto = function(){
        var promises = ajax.call([
            { methodname: 'quizaccess_videocapture_check_snapshot', args: { file: this.photo.getAttribute('src'), target: null } }
        ]);

        promises[0].done(function(j) {
            if(j.match > 0){
                var event = document.createEvent("Event");
                event.initEvent("snapshotCheckSuccess", true, true);
                document.dispatchEvent(event);
            }else{
                document.dispatchEvent(new CustomEvent("snapshotCheckFailed",
                {'detail': {'remote_error': j.remote_error, 'httpcode': j.httpcode, 'failed_attempt': j.failed_attempt}}));
            }
        }).fail(function() {
           //console.log("============ FAIL checkpicture()");
        });
    };


    RecognitionClient.prototype.checkNewPicture = function(){

        var promises = ajax.call([
            { methodname: 'quizaccess_videocapture_check_profile_picture',
              args: { file: this.photoTarget.getAttribute('src'), uid: this.uid} }
        ]);

        promises[0].done(function(j) {
            if(j.success){
                 this.photo_target.setAttribute('src', j.picture);
                 var event = document.createEvent("Event");
                 event.initEvent("newpictureCheckSuccess", true, true);
                 document.dispatchEvent(event);

            }else{
                document.dispatchEvent(new CustomEvent("newpictureCheckFailed",
                {'detail': {'remote_error': j.remote_error, 'httpcode': j.httpcode, 'msgcod': j.msgcod}}));

            }
        }).fail(function() {
            //console.log("============ FAIL checkNewPicture()");
        });

    };

    RecognitionClient.prototype.checkUploadedPicture = function(formData){
        var promises = ajax.call([
            { methodname: 'quizaccess_videocapture_check_uploaded_profile_picture',
            args: { jsonformdata: JSON.stringify(formData), uid: this.uid} }
        ]);

        promises[0].done(function(j) {
            $('#upldretry').show();
            if(j.success){
                 this.photo_target.setAttribute('src', j.picture);
                 var event = document.createEvent("Event");
                 event.initEvent("uploadedPictureCheckSuccess", true, true);
                 document.dispatchEvent(event);
            }else{
                document.dispatchEvent(new CustomEvent("ploadedPictureCheckFailed",
                {'detail': {'remote_error': j.remote_error, 'httpcode': j.httpcode, 'msgcod': j.msgcod}}));
            }
        }).fail(function() {
            //console.log("============ FAIL checkUploadedPicture()");
        });

    };

    RecognitionClient.prototype.saveandlogin = function(){

        this.takepicture();

        var promises = ajax.call([
            { methodname: 'quizaccess_videocapture_check_snapshot',
             args: { file: this.photo.getAttribute('src'), target: this.photoTarget.getAttribute('src')}
            }]);

        promises[0].done(function(j) {
            if(j.match > 0){

                promises = ajax.call([
                    { methodname: 'quizaccess_videocapture_save_profile_picture',
                      args: { file: this.photoTarget.getAttribute('src'), uid: this.uid} }
                ]);

                promises[0].done(function(jj) {
                    if(jj.success){
                        var event = document.createEvent("Event");
                        event.initEvent("saveandloginCheckSuccess", true, true);
                        document.dispatchEvent(event);
                    }else{
                        //console.log("COULD NOT SAVE PROFILE PICTURE");
                    }
                });

            }else{
                document.dispatchEvent(new CustomEvent("saveandloginCheckFailed",
                {'detail': {'remote_error': j.remote_error, 'httpcode': j.httpcode}}));
            }
        }.bind(this)).fail(function() {
           //console.log("============ FAIL saveandlogin()");
        });

    };

    RecognitionClient.prototype.clearphoto = function(){
        var context = this.canvas.getContext('2d');
        context.fillStyle = "#AAA";
        context.fillRect(0, 0, this.canvas.width, this.canvas.height);

        var data = this.canvas.toDataURL('image/png');

        this.photo.setAttribute('src', data);
    };

    RecognitionClient.prototype.formatDate = function(date){
      var result = "";

      result += date.getFullYear();
      result += "-";

      var month = date.getMonth() + 1;
      result += this.padNumber(month);
      result += "-";

      result += this.padNumber(date.getDate());
      result += " ";

      result += this.padNumber(date.getHours());
      result += ":";

      result += this.padNumber(date.getMinutes());
      result += ":";

      result += this.padNumber(date.getSeconds());

      return result;
    };

    RecognitionClient.prototype.padNumber = function(n){
        return (n > 9)?n:"0"+n;
    };


 return new RecognitionClient();


});
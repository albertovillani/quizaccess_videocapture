
import CustomEvents from 'core/custom_interaction_events';
import Modal from 'core/modal';

const SELECTORS = {
    EXIT_QUIZ_BUTTON: '[data-action="exit-quiz"]',
    TRY_AGAIN_BUTTON: '[data-action="try-again"]',
};

export default class ModalFaceCheck extends Modal {

    static TYPE = 'mod_quiz-facecheck';
    static TEMPLATE = 'quizaccess_videocapture/modal_facecheck';


    constructor (root){
        super(root);
        this.exit_link = null;
        this.rec_client = null;

    }

    registerEventListeners () {

        this.getModal().on(CustomEvents.events.activate, SELECTORS.TRY_AGAIN_BUTTON, function() {
            this.rec_client.takepicture();
            this.hide();
            this.destroy();
        }.bind(this));

        this.getModal().on(CustomEvents.events.activate, SELECTORS.EXIT_QUIZ_BUTTON, function() {
            window.location.href = this.exit_link;
        }.bind(this));
    }

    setExitLink (new_exit_link){
        this.exit_link = new_exit_link;
    }

    setRecClient (new_rc){
        this.rec_client = new_rc;
    }

    hideTryAgain (){
        this.getFooter().find(SELECTORS.TRY_AGAIN_BUTTON).hide();
    }
}
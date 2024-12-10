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
 * Modal face check class.
 *
 * @module     quizaccess_videocapture/modal_facecheck
 * @author     Alberto Villani <alberto.villani@abacotechnology.it>
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
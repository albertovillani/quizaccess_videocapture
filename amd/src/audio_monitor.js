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
 * Microphone status helper built on top of the audio VAD module.
 *
 * @module     quizaccess_videocapture/audio_monitor
 * @copyright  2024 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'quizaccess_videocapture/audio_vad'], function($, audioVAD) {

    const STATE_CLASSES = [
        'status-idle',
        'status-ready',
        'status-fallback',
        'status-error',
        'status-speaking',
        'status-silence',
    ];

    let initialised = false;
    let listenersBound = false;

    let $container = null;
    let $message = null;
    let hiddenInputSelector = null;
    let submitButtonSelector = null;
    let facematchedSelector = null;

    let speakingCallback = null;
    let silenceCallback = null;
    let inputLostCallback = null;
    let inputRestoredCallback = null;
    let speakingActive = false;
    let microphoneLost = false;
    let monitorActive = false;

    let awaitingReady = false;
    let usingFallback = false;

    const getMessage = (key, fallback) => {
        if (!$container) {
            return fallback || '';
        }
        const dataValue = $container.data(key);
        if (typeof dataValue === 'undefined') {
            return fallback || '';
        }
        return dataValue;
    };

    const setStatus = (state, message) => {
        if (!$container) {
            return;
        }
        const text = message || getMessage(state, getMessage('prompt', ''));
        $container.removeClass(STATE_CLASSES.join(' ')).addClass('status-' + state);
        if ($message) {
            $message.text(text);
        }
    };

    const getHiddenInput = () => {
        if (!hiddenInputSelector) {
            return $();
        }
        return $(hiddenInputSelector);
    };

    const getSubmitButton = () => {
        if (!submitButtonSelector) {
            return $();
        }
        return $(submitButtonSelector);
    };

    const getFaceMatchedInput = () => {
        if (!facematchedSelector) {
            return $();
        }
        return $(facematchedSelector);
    };

    const updateSubmitState = (audioAllowed) => {
        const $hidden = getHiddenInput();
        const desiredValue = audioAllowed ? '1' : '0';
        if ($hidden.length && $hidden.val() !== desiredValue) {
            $hidden.val(desiredValue).trigger('change');
        }

        const $submit = getSubmitButton();
        if (!$submit.length) {
            return;
        }

        if (!audioAllowed) {
            $submit.attr('disabled', 'disabled');
            return;
        }

        const $facematched = getFaceMatchedInput();
        if ($facematched.length && $facematched.val() !== '1') {
            return;
        }

        $submit.removeAttr('disabled');
    };

    const notifyInputRestored = () => {
        if (!microphoneLost) {
            return;
        }
        microphoneLost = false;
        if (typeof inputRestoredCallback === 'function') {
            inputRestoredCallback();
        }
    };

    const handleVadReady = () => {
        awaitingReady = false;
        usingFallback = false;
        monitorActive = true;
        setStatus('ready');
        updateSubmitState(true);
        notifyInputRestored();
    };

    const handleVadEngineReady = () => {
        usingFallback = false;
        if (!awaitingReady) {
            setStatus('ready');
        }
    };

    const handleVadEngineError = (event) => {
        usingFallback = true;
        setStatus('fallback', getMessage('fallback'));
        updateSubmitState(true);
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('VAD engine error', event && event.detail ? event.detail.error : null);
        }
    };

    const handleVadError = (event) => {
        handleInputLost(event);
        if (window.console && typeof window.console.error === 'function') {
            window.console.error('Microphone initialisation failed', event && event.detail ? event.detail.error : null);
        }
    };

    const handleVadStopped = () => {
        awaitingReady = false;
        usingFallback = false;
        speakingActive = false;
        monitorActive = false;
        const nextState = microphoneLost ? 'error' : 'idle';
        setStatus(nextState);
        updateSubmitState(false);
    };

    const handleVadSpeaking = (event) => {
        const source = event && event.detail ? event.detail.source : null;
        if (!awaitingReady) {
            setStatus('speaking');
        }
        if (source === 'energy-fallback') {
            return;
        }
        if (!speakingActive) {
            speakingActive = true;
            if (typeof speakingCallback === 'function') {
                speakingCallback();
            }
        }
    };

    const handleVadSilence = () => {
        if (!awaitingReady) {
            const message = usingFallback ? getMessage('fallback') : null;
            setStatus('silence', message);
        }
        if (speakingActive) {
            speakingActive = false;
            if (typeof silenceCallback === 'function') {
                silenceCallback();
            }
        }
    };

    const handleInputLost = (event) => {
        if (!microphoneLost) {
            microphoneLost = true;
            if (typeof inputLostCallback === 'function') {
                inputLostCallback(event && event.detail ? event.detail : {});
            }
        }
        awaitingReady = false;
        usingFallback = false;
        speakingActive = false;
        monitorActive = false;
        setStatus('error');
        updateSubmitState(false);
    };

    const handleInputUnmuted = () => {
        if (!microphoneLost) {
            return;
        }
        setStatus('ready');
        updateSubmitState(true);
        notifyInputRestored();
    };

    const bindListeners = () => {
        if (listenersBound) {
            return;
        }
        document.addEventListener('vadReady', handleVadReady);
        document.addEventListener('vadEngineReady', handleVadEngineReady);
        document.addEventListener('vadEngineError', handleVadEngineError);
        document.addEventListener('vadError', handleVadError);
        document.addEventListener('vadStopped', handleVadStopped);
        document.addEventListener('vadSpeaking', handleVadSpeaking);
        document.addEventListener('vadSilence', handleVadSilence);
        document.addEventListener('vadInputEnded', handleInputLost);
        document.addEventListener('vadInputMuted', handleInputLost);
        document.addEventListener('vadInputUnmuted', handleInputUnmuted);
        listenersBound = true;
    };

    /**
     * Initialise the helper with UI selectors.
     *
     * @param {Object} options
     */
    const init = (options) => {
        if (initialised) {
            return;
        }
        options = options || {};

        $container = options.containerSelector ? $(options.containerSelector) : null;
        if (!$container || !$container.length) {
            // Nothing to do if there is no UI container.
            return;
        }
        $message = $container.find('.audio-monitor__text');
        hiddenInputSelector = options.hiddenInputSelector || null;
        submitButtonSelector = options.submitButtonSelector || null;
        facematchedSelector = options.facematchedSelector || null;
        speakingCallback = typeof options.onSpeaking === 'function' ? options.onSpeaking : null;
        silenceCallback = typeof options.onSilence === 'function' ? options.onSilence : null;
        inputLostCallback = typeof options.onInputLost === 'function' ? options.onInputLost : null;
        inputRestoredCallback = typeof options.onInputRestored === 'function' ? options.onInputRestored : null;

        setStatus('idle');
        updateSubmitState(false);
        bindListeners();
        initialised = true;
    };

    /**
     * Start streaming audio and monitoring voice activity.
     *
     * @return {Promise<void>}
     */
    const start = () => {
        if (!$container || !$container.length) {
            return Promise.resolve();
        }

        if (monitorActive && !microphoneLost) {
            // Already streaming: keep the UI/state in sync without restarting capture.
            setStatus('ready');
            updateSubmitState(true);
            return Promise.resolve();
        }

        awaitingReady = true;
        speakingActive = false;
        updateSubmitState(false);
        setStatus('idle');

        return audioVAD.init()
            .catch((error) => {
                handleVadError({detail: {error: error}});
                return Promise.reject(error);
            });
    };

    /**
     * Stop audio capture and reset the UI.
     */
    const stop = () => {
        awaitingReady = false;
        usingFallback = false;
        speakingActive = false;
        monitorActive = false;
        setStatus('idle');
        updateSubmitState(false);
        audioVAD.stop();
    };

    return {
        init: init,
        start: start,
        stop: stop,
    };
});

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
 * Manage the face recognition process in the quiz attempt block.
 *
 * @module     quizaccess_videocapture/face_recognition_block
 * @author     Alberto Villani <alberto.villani@abacotechnology.it>
 * @copyright  2022 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Templates from 'core/templates';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';
import ModalFaceCheck from 'quizaccess_videocapture/modal_facecheck';
import RC from 'quizaccess_videocapture/face_recognition_client';
import AudioMonitor from 'quizaccess_videocapture/audio_monitor';
import Ajax from 'core/ajax';

var user_context = null;
var course = null;
var check_interval = null;
var max_failed_checks = null;
var moodleurl = null;

let violationState = {
    locked: false,
    faceviolations: {
        count: 0,
        locked: false,
        remaining: 0,
    },
    audioviolations: {
        count: 0,
        locked: false,
        remaining: 0,
    },
};

let voiceModalVisible = false;
let voiceModalResumeButton = null;
let allowVoiceModalClose = false;
let voiceModalResumeRequested = false;
let voiceViolationPending = false;

let micLostActive = false;
let micLostModalVisible = false;
let micLostModal = null;
let micLostRetryButton = null;
let micLostErrorRegion = null;
let micLostAllowClose = false;
let micLostPending = false;

let pauseFaceChecks = false;
let faceCheckTimer = null;
let faceChecksEnabled = false;
let audioMonitorEnabled = false;

const normaliseNumber = (value, fallback = 0) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const getDefaultRemaining = () => {
    return typeof max_failed_checks === 'number' ? max_failed_checks : 0;
};

const buildChannelState = (source, fallbackCount = 0, fallbackRemaining = null) => {
    const count = normaliseNumber(source && typeof source.count !== 'undefined' ? source.count : fallbackCount,
        fallbackCount);
    let remaining;
    if (source && typeof source.remaining === 'number') {
        remaining = source.remaining;
    } else if (fallbackRemaining !== null) {
        remaining = fallbackRemaining;
    } else {
        remaining = Math.max(getDefaultRemaining() - count, 0);
    }
    const locked = source && typeof source.locked !== 'undefined'
        ? !!source.locked
        : (getDefaultRemaining() > 0 && count >= getDefaultRemaining());
    return {
        count: count,
        remaining: remaining,
        locked: locked,
    };
};

const normaliseStatePayload = (state) => {
    if (!state) {
        const baseline = getDefaultRemaining();
        return {
            locked: false,
            faceviolations: {
                count: 0,
                locked: false,
                remaining: baseline,
            },
            audioviolations: {
                count: 0,
                locked: false,
                remaining: baseline,
            },
        };
    }

    const fallbackRemaining = (typeof state.remaining === 'number')
        ? state.remaining
        : (typeof state.remainingattempts === 'number' ? state.remainingattempts : null);

    const faceSource = state.faceviolations || state.face || violationState.faceviolations || null;
    const audioSource = state.audioviolations || state.audio || violationState.audioviolations || null;

    const faceFallbackCount = typeof state.failed_attempt !== 'undefined'
        ? state.failed_attempt
        : (typeof state.count !== 'undefined' ? state.count : 0);
    const face = buildChannelState(faceSource, normaliseNumber(faceFallbackCount, 0),
        fallbackRemaining);
    const audio = buildChannelState(audioSource);

    return {
        locked: typeof state.locked !== 'undefined' ? !!state.locked : (face.locked || audio.locked),
        faceviolations: face,
        audioviolations: audio,
    };
};

const setViolationState = (state) => {
    violationState = normaliseStatePayload(state);
    return violationState;
};

const updateViolationState = (state) => {
    if (!state) {
        return violationState;
    }
    return setViolationState(state);
};

const getChannelState = (state, channel) => {
    const key = channel === 'audio' ? 'audioviolations' : 'faceviolations';
    if (state && state[key]) {
        return state[key];
    }
    if (channel === 'audio' && state && state.audio) {
        return buildChannelState(state.audio);
    }
    if (channel === 'face' && state && state.face) {
        return buildChannelState(state.face);
    }
    return violationState[key];
};

const logViolation = (source) => {
    const requests = Ajax.call([{
        methodname: 'quizaccess_videocapture_log_violation',
        args: {source: source}
    }]);

    return requests[0].then((data) => {
        if (data.state) {
            return setViolationState(data.state);
        }
        return updateViolationState({
            count: data.count,
            locked: data.locked,
            remaining: data.remainingattempts
        });
    }).catch((error) => {
        if (window.console && typeof window.console.error === 'function') {
            window.console.error('Unable to log proctoring violation', error);
        }
        return null;
    });
};

const resetViolations = (source = null) => {
    const args = {};
    if (source) {
        args.source = source;
    }

    const requests = Ajax.call([{
        methodname: 'quizaccess_videocapture_reset_violations',
        args: args
    }]);

    return requests[0].then((data) => {
        if (data && data.state) {
            return setViolationState(data.state);
        }
        return setViolationState(null);
    }).catch((error) => {
        if (window.console && typeof window.console.error === 'function') {
            window.console.error('Unable to reset proctoring violations', error);
        }
        return violationState;
    });
};

const redirectToCourse = () => {
    window.location.href = moodleurl + 'course/view.php?id=' + course;
};

const exitQuiz = (options = {}) => {
    const locked = !!options.locked;
    const beforeRedirect = typeof options.beforeRedirect === 'function' ? options.beforeRedirect : null;
    if (beforeRedirect) {
        beforeRedirect();
    }

    if (!locked) {
        return Promise.resolve();
    }

    const source = Object.prototype.hasOwnProperty.call(options, 'source') ? options.source : null;
    return resetViolations(source);
};

const ensurePromise = (result) => {
    if (result && typeof result.then === 'function') {
        return Promise.resolve(result);
    }
    return Promise.resolve();
};

const getRemainingMessage = (state, channel = 'face') => {
    const channelState = getChannelState(state, channel);
    if (!channelState || typeof channelState.remaining !== 'number' || channelState.remaining <= 0) {
        return Promise.resolve('');
    }
    const stringKey = channel === 'audio' ? 'audioviolationsremaining' : 'faceviolationsremaining';
    return getString(stringKey, 'quizaccess_videocapture', channelState.remaining);
};

export const init = (user_context_id, course_id, interval, maxfailedchecks, moodle_url, enableFaceChecks = true,
        enableAudioMonitor = true, initialState = null) => {

    faceChecksEnabled = !!enableFaceChecks;
    audioMonitorEnabled = !!enableAudioMonitor;
    user_context = user_context_id;
    course = course_id;
    check_interval = interval;
    max_failed_checks = maxfailedchecks;
    moodleurl = moodle_url;
    setViolationState(initialState);

    if (faceChecksEnabled) {
        RC.setFlow('attempt');
        RC.setPhotoTarget('photo_target');
        RC.startVideoCap('video', 'canvas', 'photo');

        document.addEventListener('snapshotCheckSuccess', (event) => {
            if (event && event.detail && event.detail.state) {
                setViolationState(event.detail.state);
            } else {
                setViolationState({count: 0, locked: false});
            }
            if (!violationState.locked) {
                scheduleNextFaceCheck();
            }
        });

        document.addEventListener('snapshotCheckFailed', (e) => {
            if (pauseFaceChecks) {
                return;
            }
            if (e.detail && e.detail.state) {
                updateViolationState(e.detail.state);
            }
            modalFunction(e.detail);
        });
    }

    if (audioMonitorEnabled) {
        AudioMonitor.init({
            containerSelector: '#audio-monitor-attempt',
            onSpeaking: handleVoiceViolation,
            onSilence: handleVoiceSilence,
            onInputLost: handleMicrophoneLost,
            onInputRestored: handleMicrophoneRestored
        });
        AudioMonitor.start().catch(() => {
            // Status surfaced via monitor.
        });
    }

    if (violationState.locked) {
        pauseFaceChecks = true;
        cancelScheduledFaceCheck();
        const audioLocked = violationState.audioviolations.locked;
        const faceLocked = violationState.faceviolations.locked;
        if (audioMonitorEnabled && audioLocked) {
            showMicrophoneLostModal(violationState).catch((error) => {
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('Unable to display microphone modal on init', error);
                }
            });
        } else if (faceChecksEnabled && faceLocked) {
            modalFunction({
                failed_attempt: violationState.faceviolations.count,
                remote_error: false,
                locked: true
            });
        }
    }
};

const scheduleNextFaceCheck = (delay = check_interval) => {
    if (!faceChecksEnabled || pauseFaceChecks) {
        return;
    }
    if (faceCheckTimer) {
        window.clearTimeout(faceCheckTimer);
    }
    faceCheckTimer = window.setTimeout(() => {
        faceCheckTimer = null;
        if (!pauseFaceChecks) {
            RC.takepicture();
        }
    }, delay);
};

const cancelScheduledFaceCheck = () => {
    if (!faceChecksEnabled) {
        return;
    }
    if (faceCheckTimer) {
        window.clearTimeout(faceCheckTimer);
        faceCheckTimer = null;
    }
};

const modalFunction = async(detail = {}) => {
    if (!faceChecksEnabled) {
        return;
    }
    const stateSnapshot = (detail && detail.state) ? detail.state : violationState;
    const channelState = getChannelState(stateSnapshot, 'face');
    const remoteError = !!detail.remote_error;
    const lock = typeof detail.locked !== 'undefined'
        ? detail.locked
        : (violationState.locked || channelState.locked);
    let body = '';
    if (remoteError) {
        body = 'quizaccess_videocapture/modal_facecheck_body_api_error';
    } else {
        body = 'quizaccess_videocapture/modal_facecheck_body';
    }
    const remainingMessage = lock ? '' : await getRemainingMessage(stateSnapshot, 'face');
    const remainingText = remainingMessage ? remainingMessage : null;
    const modal = await ModalFaceCheck.create({
                    body: Templates.render(body,
                          {captured_pic: $('#photo').attr('src'), user_context: user_context, lock: lock,
                          nocachecode: Date.now(), moodleurl: moodleurl, remainingmessage: remainingText}
                  )
    });
    modal.setRecClient(RC);
    modal.setExitLink(moodleurl+'course/view.php?id='+course);
    modal.setExitHandler(() => ensurePromise(exitQuiz({locked: lock})).finally(() => redirectToCourse()));
    if (lock) {
        modal.hideTryAgain();
    }
    modal.show();
};

const handleVoiceViolation = () => {
    if (voiceModalVisible || voiceViolationPending) {
        return;
    }
    voiceViolationPending = true;
    pauseFaceChecks = true;
    cancelScheduledFaceCheck();
    const showModal = (state) => {
        return showVoiceViolationModal(state || violationState).catch((error) => {
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('Unable to display audio proctoring modal', error);
            }
        }).finally(() => {
            voiceViolationPending = false;
        });
    };
    logViolation('audio').then(showModal).catch(() => showModal(null));
};

const handleVoiceSilence = () => {
    if (violationState.audioviolations.locked) {
        return;
    }
    if (voiceModalVisible && voiceModalResumeButton) {
        voiceModalResumeButton.prop('disabled', false);
    }
};

const showVoiceViolationModal = async(state = violationState) => {
    voiceModalVisible = true;
    voiceModalResumeButton = null;
    allowVoiceModalClose = false;
    voiceModalResumeRequested = false;

    try {
        const channelState = getChannelState(state, 'audio');
        const [title, resumeLabel, exitLabel, remainingMessage] = await Promise.all([
            getString('audioviolationtitle', 'quizaccess_videocapture'),
            getString('audioviolationresume', 'quizaccess_videocapture'),
            getString('audioviolationexit', 'quizaccess_videocapture'),
            getRemainingMessage(state, 'audio')
        ]);

        const locked = channelState.locked;

        const modal = await ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            title: title,
            body: Templates.render('quizaccess_videocapture/modal_audio_violation', {
                locked: locked,
                remainingmessage: (!locked && remainingMessage) ? remainingMessage : null
            })
        });

        let footer = '<button type="button" class="btn btn-secondary" data-action="exit-quiz">' + exitLabel + '</button>';
        if (!locked) {
            footer = '<button type="button" class="btn btn-primary" data-action="resume-quiz" disabled="disabled">' +
                resumeLabel + '</button>' + footer;
        }
        modal.setFooter(footer);

        const root = modal.getRoot();
        root.find('[data-action="hide"]').hide();
        voiceModalResumeButton = locked ? null : root.find('[data-action="resume-quiz"]');

        if (voiceModalResumeButton) {
            root.on('click', '[data-action="resume-quiz"]', (event) => {
                event.preventDefault();
                if (voiceModalResumeButton && voiceModalResumeButton.is(':disabled')) {
                    return;
                }
                allowVoiceModalClose = true;
                voiceModalResumeRequested = true;
                modal.hide();
            });
        }

        root.on('click', '[data-action="exit-quiz"]', (event) => {
            event.preventDefault();
            ensurePromise(exitQuiz({
                locked: locked,
                beforeRedirect: () => {
                    allowVoiceModalClose = true;
                    voiceModalResumeRequested = false;
                },
            })).finally(() => redirectToCourse());
        });

        root.on(ModalEvents.hidden, () => {
            voiceModalVisible = false;
            voiceModalResumeButton = null;
            modal.destroy();
            const shouldReopen = !allowVoiceModalClose;
            allowVoiceModalClose = false;
            if (voiceModalResumeRequested && !micLostActive) {
                pauseFaceChecks = false;
                scheduleNextFaceCheck();
            }
            voiceModalResumeRequested = false;
            if (shouldReopen) {
                handleVoiceViolation();
            } else if (micLostActive && !micLostModalVisible) {
                handleMicrophoneLost();
            }
        });

        modal.show();
        return modal;
    } catch (error) {
        voiceModalVisible = false;
        voiceModalResumeButton = null;
        allowVoiceModalClose = false;
        voiceModalResumeRequested = false;
        throw error;
    }
};

const handleMicrophoneLost = () => {
    if (!audioMonitorEnabled || micLostModalVisible || micLostPending) {
        return;
    }
    micLostPending = true;
    micLostActive = true;
    pauseFaceChecks = true;
    cancelScheduledFaceCheck();
    const showModal = (state) => {
        return showMicrophoneLostModal(state || violationState).catch((error) => {
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('Unable to display microphone access modal', error);
            }
        }).finally(() => {
            micLostPending = false;
        });
    };
    logViolation('microphone').then(showModal).catch(() => showModal(null));
};

const handleMicrophoneRestored = () => {
    if (violationState.locked) {
        return;
    }
    micLostActive = false;
    if (micLostModalVisible && micLostModal) {
        micLostAllowClose = true;
        micLostModal.hide();
        return;
    }
    if (!voiceModalVisible && faceChecksEnabled) {
        pauseFaceChecks = false;
        scheduleNextFaceCheck();
    }
};

const showMicrophoneLostModal = async(state = violationState) => {
    micLostModalVisible = true;
    micLostAllowClose = false;
    micLostRetryButton = null;
    micLostErrorRegion = null;

    try {
        const channelState = getChannelState(state, 'audio');
        const [title, retryLabel, exitLabel, remainingMessage] = await Promise.all([
            getString('audiomonitorlosttitle', 'quizaccess_videocapture'),
            getString('audiomonitorlostretry', 'quizaccess_videocapture'),
            getString('audiomonitorlostexit', 'quizaccess_videocapture'),
            getRemainingMessage(state, 'audio'),
        ]);

        const locked = channelState.locked;

        const modal = await ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            title: title,
            body: Templates.render('quizaccess_videocapture/modal_audio_monitor_block', {
                locked: locked,
                remainingmessage: (!locked && remainingMessage) ? remainingMessage : null
            })
        });

    micLostModal = modal;
    let footer = '<button type="button" class="btn btn-secondary" data-action="exit-quiz">' + exitLabel + '</button>';
    if (!locked) {
        footer = '<button type="button" class="btn btn-primary" data-action="retry-mic">' + retryLabel + '</button>' + footer;
    }
    modal.setFooter(footer);

    const root = modal.getRoot();
    root.find('[data-action="hide"]').hide();
    micLostRetryButton = locked ? null : root.find('[data-action="retry-mic"]');
    micLostErrorRegion = root.find('[data-region="mic-error"]');
    const micLockedRegion = root.find('[data-region="mic-locked"]');
    const micRemainingRegion = root.find('[data-region="mic-remaining"]');
    const micDefaultMessage = root.find('[data-region="mic-message"]');
    if (locked) {
        micLockedRegion.show();
        if (micDefaultMessage) {
            micDefaultMessage.hide();
        }
    } else if (micRemainingRegion && remainingMessage) {
        micRemainingRegion.text(remainingMessage).show();
    }

    const setRetryState = (disabled) => {
        if (!micLostRetryButton) {
            return;
        }
        if (disabled) {
            micLostRetryButton.attr('disabled', 'disabled');
        } else {
            micLostRetryButton.removeAttr('disabled');
        }
    };

    if (micLostRetryButton) {
        root.on('click', '[data-action="retry-mic"]', (event) => {
            event.preventDefault();
            if (!micLostRetryButton || micLostRetryButton.is(':disabled')) {
                return;
            }
            if (micLostErrorRegion) {
                micLostErrorRegion.hide();
            }
            setRetryState(true);
            AudioMonitor.start().catch(() => {
                setRetryState(false);
                if (micLostErrorRegion) {
                    micLostErrorRegion.show();
                }
            });
        });
    }

    root.on('click', '[data-action="exit-quiz"]', (event) => {
        event.preventDefault();
        ensurePromise(exitQuiz({
            locked: locked,
            beforeRedirect: () => {
                micLostAllowClose = true;
            },
        })).finally(() => redirectToCourse());
    });

    root.on(ModalEvents.hidden, () => {
        micLostModalVisible = false;
        micLostModal = null;
        micLostRetryButton = null;
        micLostErrorRegion = null;
        const shouldReopen = micLostActive && !micLostAllowClose;
        micLostAllowClose = false;
        modal.destroy();
        if (shouldReopen) {
            handleMicrophoneLost();
        } else if (!micLostActive && !voiceModalVisible && faceChecksEnabled) {
            pauseFaceChecks = false;
            scheduleNextFaceCheck();
        }
    });

        modal.show();
        return modal;
    } catch (error) {
        micLostModalVisible = false;
        micLostAllowClose = false;
        micLostRetryButton = null;
        micLostErrorRegion = null;
        throw error;
    }
};

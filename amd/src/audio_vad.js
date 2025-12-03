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
 * Browser-side WebRTC based voice activity detection helper.
 *
 * @module     quizaccess_videocapture/audio_vad
 * @copyright  2024 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    const DEFAULT_OPTIONS = {
        targetSampleRate: 16000,
        processorBufferSize: 1024,
        frameDurationMs: 30,
        vadMode: 3,
        speakingFrameCount: 3,
        silenceFrameCount: 6,
        energyThreshold: 0.01,
        vadEnergyGate: 0.025,
        noiseCalibrationDurationMs: 1200,
        noiseCalibrationMaxDurationMs: 4000,
        noiseFloorSmoothing: 0.92,
        speechEnergyRatio: 3,
        speechEnergyMargin: 0.005,
    };

    const ALLOWED_FRAME_DURATIONS = [10, 20, 30];
    const ALLOWED_FRAME_SAMPLES = [160, 320, 480];

    /**
     * Fire a DOM CustomEvent with an optional payload.
     *
     * @param {string} name
     * @param {Object} detail
     */
    const dispatchEvent = (name, detail = {}) => {
        document.dispatchEvent(new CustomEvent(name, {detail: detail}));
    };

    /**
     * Convert Float32 samples to Int16 PCM.
     *
     * @param {Float32Array} buffer
     * @return {Int16Array}
     */
    const floatTo16BitPCM = (buffer) => {
        const output = new Int16Array(buffer.length);
        for (let i = 0; i < buffer.length; i++) {
            const s = Math.max(-1, Math.min(1, buffer[i]));
            output[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
        }
        return output;
    };

    /**
     * Down-sample a buffer to the target rate.
     *
     * @param {Float32Array} inputBuffer
     * @param {number} inputSampleRate
     * @param {number} targetSampleRate
     * @return {Int16Array}
     */
    const downsampleBuffer = (inputBuffer, inputSampleRate, targetSampleRate) => {
        if (inputSampleRate === targetSampleRate) {
            return floatTo16BitPCM(inputBuffer);
        }

        const sampleRateRatio = inputSampleRate / targetSampleRate;
        const newLength = Math.round(inputBuffer.length / sampleRateRatio);
        const result = new Float32Array(newLength);
        let offsetResult = 0;
        let offsetBuffer = 0;

        while (offsetResult < result.length) {
            const nextOffsetBuffer = Math.round((offsetResult + 1) * sampleRateRatio);
            let accumulator = 0;
            let count = 0;
            for (let i = offsetBuffer; i < nextOffsetBuffer && i < inputBuffer.length; i++) {
                accumulator += inputBuffer[i];
                count++;
            }
            result[offsetResult] = accumulator / (count || 1);
            offsetResult++;
            offsetBuffer = nextOffsetBuffer;
        }
        return floatTo16BitPCM(result);
    };

    /**
     * Compute RMS to keep a lightweight fallback detector.
     *
     * @param {Float32Array} buffer
     * @return {number}
     */
    const computeRms = (buffer) => {
        let sum = 0;
        for (let i = 0; i < buffer.length; i++) {
            sum += buffer[i] * buffer[i];
        }
        return Math.sqrt(sum / buffer.length);
    };

    /**
     * Compute RMS for Int16 PCM data normalised to [-1, 1].
     *
     * @param {Int16Array} buffer
     * @return {number}
     */
    const computePcmRms = (buffer) => {
        let sum = 0;
        for (let i = 0; i < buffer.length; i++) {
            const sample = buffer[i] / 32768;
            sum += sample * sample;
        }
        return Math.sqrt(sum / buffer.length);
    };

    let vadModulePromise = null;

    /**
     * Lazy load the libfvad WASM runtime.
     *
     * @return {Promise<Object>}
     */
    const loadVadRuntime = () => {
        if (vadModulePromise) {
            return vadModulePromise;
        }
        const candidateSources = (() => {
            const sources = [];
            const primaryLoaderUrl = require.toUrl('quizaccess_videocapture/fvad.mjs');
            let directLoaderUrl = null;
            let directWasmUrl = null;

            if (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) {
                const base = M.cfg.wwwroot.replace(/\/$/, '');
                const jsrev = typeof M.cfg.jsrev !== 'undefined' ? M.cfg.jsrev : '';
                const versionSuffix = jsrev ? '?v=' + encodeURIComponent(jsrev) : '';
                const assetBase = base + '/mod/quiz/accessrule/videocapture/amd/build/';
                directLoaderUrl = assetBase + 'fvad.mjs' + versionSuffix;
                directWasmUrl = assetBase + 'fvad.wasm' + versionSuffix;
            }

            sources.push({
                loaderUrl: primaryLoaderUrl,
                wasmUrl: directWasmUrl || require.toUrl('quizaccess_videocapture/fvad.wasm'),
            });

            if (directLoaderUrl && sources.every((source) => source.loaderUrl !== directLoaderUrl)) {
                sources.push({
                    loaderUrl: directLoaderUrl,
                    wasmUrl: directWasmUrl,
                });
            }

            return sources;
        })();

        const dynamicImport = (path) => {
            // eslint-disable-next-line no-new-func
            return new Function('modulePath', 'return import(modulePath);')(path);
        };

        const resolveFactory = (value, depth = 0) => {
            if (!value || depth > 5) {
                return null;
            }
            if (typeof value === 'function') {
                return value;
            }
            if (value && typeof value.then === 'function') {
                return value.then((resolved) => resolveFactory(resolved, depth + 1));
            }
            if (typeof value === 'object') {
                if (typeof value.default !== 'undefined') {
                    return resolveFactory(value.default, depth + 1);
                }
                if (typeof value.fvad !== 'undefined') {
                    return resolveFactory(value.fvad, depth + 1);
                }
            }
            return null;
        };

        const importWithFallback = (sources, index = 0) => {
            const currentSource = sources[index];
            return dynamicImport(currentSource.loaderUrl).then((module) => {
                const maybeFactory = resolveFactory(module);

                if (maybeFactory) {
                    return {
                        maybeFactory: maybeFactory,
                        source: currentSource,
                    };
                }

                if (index + 1 < sources.length) {
                    // eslint-disable-next-line no-console
                    console.warn('VAD module missing factory exports via', currentSource.loaderUrl);
                    return importWithFallback(sources, index + 1);
                }

                // eslint-disable-next-line no-console
                console.error('Unexpected VAD module export shape', module, Object.keys(module || {}));
                throw new Error('Unable to load libfvad factory');
            }).catch((error) => {
                if (index + 1 < sources.length) {
                    // eslint-disable-next-line no-console
                    console.warn('VAD module import failed via', currentSource.loaderUrl, error);
                    return importWithFallback(sources, index + 1);
                }
                throw error;
            });
        };

        const instantiateRuntime = (factoryCandidate, source) => {
            const resolveLocate = (path) => {
                if (/\.wasm(?:\?.*)?$/.test(path) && source && source.wasmUrl) {
                    return source.wasmUrl;
                }
                return require.toUrl('quizaccess_videocapture/' + path);
            };
            const initialiseWithLocate = (factory) => factory({
                locateFile: resolveLocate,
            });

            if (typeof factoryCandidate === 'function') {
                return initialiseWithLocate(factoryCandidate);
            }

            // Promise branch.
            return factoryCandidate.then((factory) => initialiseWithLocate(factory));
        };

        vadModulePromise = importWithFallback(candidateSources).then((result) => {
            return instantiateRuntime(result.maybeFactory, result.source);
        });
        return vadModulePromise;
    };

    /**
     * Voice activity detector harness.
     *
     * @param {Object} options
     * @constructor
     */
    const AudioVAD = function(options = {}) {
        this.options = Object.assign({}, DEFAULT_OPTIONS, options);
        if (ALLOWED_FRAME_DURATIONS.indexOf(this.options.frameDurationMs) === -1) {
            this.options.frameDurationMs = DEFAULT_OPTIONS.frameDurationMs;
        }
        this.options.frameSamples = Math.round(
            (this.options.targetSampleRate * this.options.frameDurationMs) / 1000
        );
        if (ALLOWED_FRAME_SAMPLES.indexOf(this.options.frameSamples) === -1) {
            this.options.frameDurationMs = DEFAULT_OPTIONS.frameDurationMs;
            this.options.frameSamples = 480;
        }

        this.audioContext = null;
        this.mediaStream = null;
        this.streamSource = null;
        this.processorNode = null;
        this.inputSampleRate = null;

        this._onFrame = null;
        this._vadInitPromise = null;
        this.vad = null;
        this.useEnergyFallback = false;

        this.pendingPcmChunks = [];
        this._pcmRemainder = new Int16Array(0);

        this.speaking = false;
        this.consecutiveSpeakingFrames = 0;
        this.consecutiveSilenceFrames = 0;

        this.noiseFloor = this.options.energyThreshold;
        this.noiseFloorAccumulator = 0;
        this.noiseModelReady = false;
        this.noiseCalibrationFrames = Math.max(
            1,
            Math.round(this.options.noiseCalibrationDurationMs / this.options.frameDurationMs)
        );
        this.noiseCalibrationTimeoutFrames = Math.max(
            this.noiseCalibrationFrames,
            Math.round(this.options.noiseCalibrationMaxDurationMs / this.options.frameDurationMs)
        );
        this.noiseCalibrationFramesSeen = 0;
        this.totalFramesProcessed = 0;
        this._trackEventHandlers = [];
    };

    /**
     * Initialise capture and load the WebRTC VAD engine.
     *
     * @param {Function} onFrame
     * @return {Promise<void>}
     */
    AudioVAD.prototype.init = function(onFrame) {
        if (this.audioContext) {
            return Promise.resolve();
        }
        this._onFrame = onFrame;

        return navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            },
            video: false,
        }).then((stream) => {
            this.mediaStream = stream;
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.inputSampleRate = this.audioContext.sampleRate;

            this._bindTrackEvents(stream);
            this.streamSource = this.audioContext.createMediaStreamSource(stream);
            this.processorNode = this.audioContext.createScriptProcessor(
                this.options.processorBufferSize, 1, 1
            );
            this.processorNode.onaudioprocess = this._processAudio.bind(this);
            this.streamSource.connect(this.processorNode);
            this.processorNode.connect(this.audioContext.destination);

            this._initVad();
            dispatchEvent('vadReady');
        }).catch((error) => {
            dispatchEvent('vadError', {error: error});
            throw error;
        });
    };

    /**
     * Load and initialise the libfvad engine.
     *
     * @return {Promise<void>}
     * @private
     */
    AudioVAD.prototype._initVad = function() {
        if (this._vadInitPromise) {
            return this._vadInitPromise;
        }

        this._vadInitPromise = loadVadRuntime()
            .then((runtime) => {
                const create = runtime.cwrap('fvad_new', 'number', []);
                const free = runtime.cwrap('fvad_free', 'void', ['number']);
                const reset = runtime.cwrap('fvad_reset', 'void', ['number']);
                const setMode = runtime.cwrap('fvad_set_mode', 'number', ['number', 'number']);
                const setSampleRate = runtime.cwrap('fvad_set_sample_rate', 'number', ['number', 'number']);
                const process = runtime.cwrap('fvad_process', 'number', ['number', 'number', 'number']);

                const handle = create();
                if (handle === 0) {
                    throw new Error('Failed to allocate VAD handle');
                }
                if (setSampleRate(handle, this.options.targetSampleRate) !== 0) {
                    free(handle);
                    throw new Error('Unsupported VAD sample rate');
                }
                if (setMode(handle, this.options.vadMode) !== 0) {
                    free(handle);
                    throw new Error('Unable to configure VAD aggressiveness');
                }

                this.vad = {
                    runtime: runtime,
                    handle: handle,
                    free: free,
                    reset: reset,
                    process: process,
                };
            dispatchEvent('vadEngineReady', {mode: this.options.vadMode});

            if (this.pendingPcmChunks.length) {
                const queued = this.pendingPcmChunks.slice();
                this.pendingPcmChunks = [];
                queued.forEach((chunk) => this._consumePcmBuffer(chunk));
                }
            })
            .catch((error) => {
                this.useEnergyFallback = true;
                dispatchEvent('vadEngineError', {error: error});
                throw error;
            });

        return this._vadInitPromise;
    };

    /**
     * Bind MediaStreamTrack lifecycle events so we can react when audio input disappears.
     *
     * @param {MediaStream} stream
     * @private
     */
    AudioVAD.prototype._bindTrackEvents = function(stream) {
        this._unbindTrackEvents();
        if (!stream || typeof stream.getAudioTracks !== 'function') {
            return;
        }

        const tracks = stream.getAudioTracks();
        tracks.forEach((track) => {
            if (!track) {
                return;
            }
            const endedHandler = () => {
                dispatchEvent('vadInputEnded', {trackId: track.id || null});
                this.stop();
            };
            const muteHandler = () => {
                dispatchEvent('vadInputMuted', {trackId: track.id || null});
            };
            const unmuteHandler = () => {
                dispatchEvent('vadInputUnmuted', {trackId: track.id || null});
            };

            track.addEventListener('ended', endedHandler);
            track.addEventListener('mute', muteHandler);
            track.addEventListener('unmute', unmuteHandler);
            this._trackEventHandlers.push({
                track: track,
                endedHandler: endedHandler,
                muteHandler: muteHandler,
                unmuteHandler: unmuteHandler,
            });
        });
    };

    /**
     * Remove any MediaStreamTrack listeners attached during capture.
     *
     * @private
     */
    AudioVAD.prototype._unbindTrackEvents = function() {
        if (!this._trackEventHandlers) {
            this._trackEventHandlers = [];
            return;
        }
        this._trackEventHandlers.forEach((entry) => {
            const track = entry.track;
            if (!track) {
                return;
            }
            if (entry.endedHandler) {
                track.removeEventListener('ended', entry.endedHandler);
            }
            if (entry.muteHandler) {
                track.removeEventListener('mute', entry.muteHandler);
            }
            if (entry.unmuteHandler) {
                track.removeEventListener('unmute', entry.unmuteHandler);
            }
        });
        this._trackEventHandlers = [];
    };

    /**
     * Stop capture and dispose resources.
     */
    AudioVAD.prototype.stop = function() {
        this._unbindTrackEvents();
        if (this.processorNode) {
            this.processorNode.disconnect();
            this.processorNode.onaudioprocess = null;
            this.processorNode = null;
        }
        if (this.streamSource) {
            this.streamSource.disconnect();
            this.streamSource = null;
        }
        if (this.mediaStream) {
            this.mediaStream.getTracks().forEach((track) => track.stop());
            this.mediaStream = null;
        }
        if (this.audioContext) {
            this.audioContext.close();
            this.audioContext = null;
        }

        this.pendingPcmChunks = [];
        this._pcmRemainder = new Int16Array(0);
        this.consecutiveSpeakingFrames = 0;
        this.consecutiveSilenceFrames = 0;
        this.speaking = false;

        this.noiseFloor = this.options.energyThreshold;
        this.noiseFloorAccumulator = 0;
        this.noiseModelReady = false;
        this.noiseCalibrationFramesSeen = 0;
        this.totalFramesProcessed = 0;

        if (this.vad && this.vad.handle) {
            this.vad.free(this.vad.handle);
        }
        this.vad = null;
        this._vadInitPromise = null;
        this.useEnergyFallback = false;

        dispatchEvent('vadStopped');
    };

    /**
     * Process audio frames coming from ScriptProcessorNode.
     *
     * @param {AudioProcessingEvent} audioEvent
     * @private
     */
    AudioVAD.prototype._processAudio = function(audioEvent) {
        const floatBuffer = audioEvent.inputBuffer.getChannelData(0);
        const pcm16Buffer = downsampleBuffer(
            floatBuffer,
            this.inputSampleRate,
            this.options.targetSampleRate
        );

        if (this._onFrame) {
            this._onFrame(pcm16Buffer);
        }

        if (this.vad && this.vad.handle) {
            this._consumePcmBuffer(pcm16Buffer);
        } else if (this.useEnergyFallback) {
            this._energyFallback(floatBuffer);
        } else {
            this.pendingPcmChunks.push(pcm16Buffer);
        }
    };

    /**
     * Queue PCM buffers and feed the VAD in fixed frame windows.
     *
     * @param {Int16Array} pcm
     * @private
     */
    AudioVAD.prototype._consumePcmBuffer = function(pcm) {
        let buffer;
        if (this._pcmRemainder.length === 0) {
            buffer = pcm;
        } else {
            buffer = new Int16Array(this._pcmRemainder.length + pcm.length);
            buffer.set(this._pcmRemainder, 0);
            buffer.set(pcm, this._pcmRemainder.length);
        }

        const frameLength = this.options.frameSamples;
        let offset = 0;
        while (offset + frameLength <= buffer.length) {
            const frame = buffer.subarray(offset, offset + frameLength);
            this._evaluateVadFrame(frame);
            offset += frameLength;
        }

        if (offset < buffer.length) {
            const remaining = new Int16Array(buffer.length - offset);
            remaining.set(buffer.subarray(offset));
            this._pcmRemainder = remaining;
        } else {
            this._pcmRemainder = new Int16Array(0);
        }
    };

    /**
     * Run a single frame through libfvad.
     *
     * @param {Int16Array} frame
     * @private
     */
    AudioVAD.prototype._evaluateVadFrame = function(frame) {
        if (!this.vad || !this.vad.handle) {
            return;
        }

        const runtime = this.vad.runtime;
        const pointer = runtime._malloc(frame.length * frame.BYTES_PER_ELEMENT);
        runtime.HEAP16.set(frame, pointer / 2);
        const result = this.vad.process(this.vad.handle, pointer, frame.length);
        runtime._free(pointer);

        if (result !== 0 && result !== 1) {
            dispatchEvent('vadEngineError', {error: new Error('Unexpected VAD result: ' + result)});
            return;
        }

        const rms = computePcmRms(frame);
        let isSpeech = Boolean(result);
        const detail = {
            source: 'webrtc-vad',
            frameDurationMs: this.options.frameDurationMs,
            rms: rms,
        };

        if (isSpeech && !this._passesEnergyGate(rms)) {
            isSpeech = false;
            detail.gate = 'dynamic';
        }

        this._updateNoiseModel(rms, isSpeech);
        this._updateSpeakingState(isSpeech, detail);
    };

    /**
     * Fallback RMS gate used if the WASM engine is unavailable.
     *
     * @param {Float32Array} buffer
     * @private
     */
    AudioVAD.prototype._energyFallback = function(buffer) {
        const rms = computeRms(buffer);
        const candidateSpeech = rms >= this.options.energyThreshold;
        const passesGate = candidateSpeech && this._passesEnergyGate(rms);
        this._updateNoiseModel(rms, passesGate);
        this._updateSpeakingState(passesGate, {
            source: 'energy-fallback',
            rms: rms,
            gate: passesGate ? undefined : 'dynamic',
        });
    };

    /**
     * Debounce speaking/silent transitions and emit events.
     *
     * @param {boolean} isSpeech
     * @param {Object} detail
     * @private
     */
    AudioVAD.prototype._updateSpeakingState = function(isSpeech, detail = {}) {
        if (isSpeech) {
            this.consecutiveSpeakingFrames++;
            this.consecutiveSilenceFrames = 0;
        } else {
            this.consecutiveSilenceFrames++;
            this.consecutiveSpeakingFrames = 0;
        }

        if (!this.speaking && this.consecutiveSpeakingFrames >= this.options.speakingFrameCount) {
            this.speaking = true;
            dispatchEvent('vadSpeaking', detail);
        } else if (this.speaking && this.consecutiveSilenceFrames >= this.options.silenceFrameCount) {
            this.speaking = false;
            dispatchEvent('vadSilence', detail);
        }
    };

    /**
     * Determine whether the frame RMS passes the adaptive gate.
     *
     * @param {number} rms
     * @return {boolean}
     * @private
     */
    AudioVAD.prototype._passesEnergyGate = function(rms) {
        if (!isFinite(rms)) {
            return true;
        }
        let dynamicGate = this.options.vadEnergyGate;
        if (this.noiseModelReady) {
            const adaptiveGate = (this.noiseFloor * this.options.speechEnergyRatio) + this.options.speechEnergyMargin;
            dynamicGate = Math.max(dynamicGate, adaptiveGate);
        }
        return rms >= dynamicGate;
    };

    /**
     * Update the adaptive noise model so we can gate out background hum.
     *
     * @param {number} rms
     * @param {boolean} isSpeech
     * @private
     */
    AudioVAD.prototype._updateNoiseModel = function(rms, isSpeech) {
        if (!isFinite(rms)) {
            return;
        }

        this.totalFramesProcessed++;

        if (!this.noiseModelReady) {
            if (!isSpeech) {
                this.noiseFloorAccumulator += rms;
                this.noiseCalibrationFramesSeen++;
                this.noiseFloor = this.noiseFloorAccumulator / this.noiseCalibrationFramesSeen;
            }

            if (this.noiseCalibrationFramesSeen >= this.noiseCalibrationFrames ||
                    this.totalFramesProcessed >= this.noiseCalibrationTimeoutFrames) {
                this.noiseModelReady = true;
                if (this.noiseFloor < this.options.energyThreshold) {
                    this.noiseFloor = this.options.energyThreshold;
                }
            }
            return;
        }

        if (!isSpeech) {
            const smoothing = this.options.noiseFloorSmoothing;
            this.noiseFloor = (smoothing * this.noiseFloor) + ((1 - smoothing) * rms);
        }

        if (this.noiseFloor < this.options.energyThreshold) {
            this.noiseFloor = this.options.energyThreshold;
        }
    };

    return new AudioVAD();
});

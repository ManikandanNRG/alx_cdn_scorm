/**
 * Bridge Controller for local_alx_cdn_scorm.
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ScormApi from 'local_alx_cdn_scorm/scorm_api';

let state = {
    scormid: 0,
    scoid: 0,
    attempt: 0,
    api: null,
    data: {},
    debug: false
};

const log = (msg) => {
    if (state.debug && console && console.log) {
        console.log("[Bridge] " + msg);
    }
};

const getValue = (element) => {
    if (typeof state.data[element] !== 'undefined') {
        return state.data[element];
    }
    return "";
};

const setValue = (element, value) => {
    state.data[element] = value;
    return "true";
};

const loadData = () => {
    log("Loading user data for resume...");
    Ajax.call([{
        methodname: 'local_alx_cdn_scorm_get_user_tracks',
        args: { scormid: state.scormid, scoid: state.scoid, attempt: state.attempt }
    }])[0].done(function (response) {
        log("Data loaded: " + response.tracks.length + " items");
        if (response.tracks) {
            response.tracks.forEach(function (track) {
                state.data[track.element] = track.value;
            });
        }
    }).fail(function (ex) {
        log("Data load failed: " + JSON.stringify(ex));
    });
};

const commit = () => {
    log("Committing data to server...");

    const tracks = [];
    for (const key in state.data) {
        tracks.push({
            element: key,
            value: state.data[key]
        });
    }

    if (tracks.length === 0) return "true";

    Ajax.call([{
        methodname: 'local_alx_cdn_scorm_save_tracks',
        args: { scormid: state.scormid, scoid: state.scoid, attempt: state.attempt, tracks: tracks }
    }])[0].done(function (response) {
        log("Save success: " + JSON.stringify(response));
    }).fail(function (ex) {
        log("Save failed: " + JSON.stringify(ex));
    });

    return "true";
};

const handleMessage = (event) => {
    const iframe = document.getElementById('scorm_object');
    if (iframe && event.source !== iframe.contentWindow) {
        log("Security Warning: Received message from unknown source.");
        return;
    }

    if (!event.data) return;

    let msg = event.data;
    if (typeof msg === 'string') {
        try {
            msg = JSON.parse(msg);
        } catch (e) {
            return;
        }
    }

    const response = {
        type: msg.type + "Response",
        requestId: msg.requestId
    };

    switch (msg.type) {
        case 'LMSInitialize':
            response.result = state.api.LMSInitialize(msg.param);
            break;
        case 'LMSGetValue':
            response.result = state.api.LMSGetValue(msg.element);
            break;
        case 'LMSSetValue':
            response.result = state.api.LMSSetValue(msg.element, msg.value);
            break;
        case 'LMSCommit':
            response.result = state.api.LMSCommit(msg.param);
            break;
        case 'LMSFinish':
            response.result = state.api.LMSFinish(msg.param);
            break;
        case 'LMSGetLastError':
            response.result = state.api.LMSGetLastError();
            break;
        default:
            return;
    }

    if (event.source) {
        event.source.postMessage(JSON.stringify(response), "*");
    }
};

export const init = (params) => {
    state.scormid = params.scormid;
    state.scoid = params.scoid;
    state.cmid = params.cmid;
    state.attempt = params.attempt;
    state.debug = params.debug || false;

    const controllerInterface = {
        log: log,
        getValue: getValue,
        setValue: setValue,
        commit: commit
    };

    state.api = new ScormApi(controllerInterface);
    window.API = state.api;
    window.API_1484_11 = state.api;

    window.addEventListener("message", handleMessage, false);

    log("Bridge Controller Initialized (ES6) for SCORM " + state.scormid);

    loadData();
};

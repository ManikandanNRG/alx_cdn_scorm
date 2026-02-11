/**
 * SCORM API Implementation for the Bridge.
 */
export default class ScormApi {
    constructor(controller) {
        this.controller = controller;
        this.LMSInitialized = false;
    }

    LMSInitialize(param) {
        this.LMSInitialized = true;
        this.controller.log("LMSInitialize called");
        return "true";
    }

    LMSFinish(param) {
        this.LMSInitialized = false;
        this.controller.log("LMSFinish called");
        this.controller.commit();
        return "true";
    }

    LMSGetValue(element) {
        this.controller.log("LMSGetValue: " + element);
        return this.controller.getValue(element);
    }

    LMSSetValue(element, value) {
        this.controller.log("LMSSetValue: " + element + " = " + value);
        return this.controller.setValue(element, value);
    }

    LMSCommit(param) {
        this.controller.log("LMSCommit called");
        return this.controller.commit();
    }

    LMSGetLastError() { return "0"; }
    LMSGetErrorString(errorCode) { return "No error"; }
    LMSGetDiagnostic(errorCode) { return "No diagnostic"; }
}

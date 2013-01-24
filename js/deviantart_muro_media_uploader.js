(function (window, undefined) {
"use strict";

var muroContainer = window.document.getElementsByClassName("muro-container")[0],
    muroIframe    = muroContainer.getElementsByClassName("muro")[0],
    loadingDiv    = muroContainer.getElementsByClassName("muro-loading")[0],
    savingDiv     = muroContainer.getElementsByClassName("muro-saving")[0],
    waitingForMuro = false,
    waitingForWP   = false,
    doneCallback   = null;

function uploadImageData(base64img, success, fail) {
    var defaults = window._wpPluploadSettings.defaults,
        files    = {};

    files[defaults.file_data_name] = {
        base64data:   base64img,
        filename:     'deviantart_muro_drawing.png',
        content_type: 'image/png'
        };

    window.deviantart_muro_uploader({
        url:          defaults.url,
        extra_params: defaults.multipart_params,
        files:        files,
        success:      success,
        fail:         fail
        });
}

function upstreamMessage(event, data) {
    var message = {
        type:  'damuro',
        event: event
        };
    if (data !== undefined) {
        message.data = data;
    }
    window.parent.postMessage(message, '*');
}

// TODO: Hacky hacky, should make this cleaner.
function runWhenDone(callback) {
    // First-come first-served: hack because muro save can error independent of WP save
    if (callback && !doneCallback) {
        doneCallback = callback;
    }
    if (!waitingForMuro && !waitingForWP) {
        callback = doneCallback;
        doneCallback = null;
        callback();
    }
}

function receiveMessage(message) {
    if (message.source !== muroIframe.contentWindow) {
        return; // Not from our iframe, ignore it.
    }

    switch (message.data.type) {
    case 'ready':
        muroIframe.style.visibility = 'visible';
        loadingDiv.style.visibility = 'hidden';
        return;
    case 'cancel':
        upstreamMessage('cancel');
        return;
    case 'done':
        var image = message.data.image;

        savingDiv.style.visibility  = 'visible';
        muroIframe.style.visibility = 'hidden';
        waitingForMuro = true;
        waitingForWP   = true;
        uploadImageData(image.replace(/^data:image\/png;base64,/, ''),
            function (xhr) {
                // debug: muroContainer.innerHTML = "<div><h1>Upload complete</h1>" + xhr.response + "</div>";
                waitingForWP = false;
                runWhenDone(function () {
                        // pass JSON to parent media modal iframe
                        upstreamMessage('upload', JSON.parse(xhr.response));
                    });
            },
            function (xhr) {
                waitingForWP = false;
                runWhenDone(function () {
                        // TODO: report the error details too.
                        upstreamMessage('error', {
                            error: "Unable to save deviantART muro image as WordPress attachment"
                            });
                    });
            });
        return;
    case 'error':
        waitingForMuro = false;
        runWhenDone(function () {
                upstreamMessage('error', {
                    error: message.data.error
                });
            });
        return;
    case 'complete':
        waitingForMuro = false;
        runWhenDone(function () {
                upstreamMessage('error', {
                    error: message.data.error
                    });
            });
        return;
    }
}
window.addEventListener("message", receiveMessage, false);

})(window);

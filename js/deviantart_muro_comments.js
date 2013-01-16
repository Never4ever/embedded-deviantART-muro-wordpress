(function (window, undefined) {
"use strict";

// TODO: build list of shortcode iframes
// TODO: find comment form upwards from addImageButton
// TODO: display "deviantART muro comments are not enabled" splashes if comment form not found

// Oh, the horrors involved in keeping dependency-free... #FirstWorldProblems
var muroModalContainer = window.document.getElementsByClassName("muro-modal-container")[0],
    muroContainer  = muroModalContainer.getElementsByClassName("muro-container")[0],
    muroIframe     = muroContainer.getElementsByClassName("muro")[0],
    loadingDiv     = muroContainer.getElementsByClassName("muro-loading")[0],
    savingDiv      = muroContainer.getElementsByClassName("muro-saving")[0],
    imageStore     = window.document.getElementsByClassName("deviantart-muro-add-comment-store")[0],
    previewDiv     = window.document.getElementsByClassName("deviantart-muro-comment-image-preview")[0],
    previewImg     = previewDiv.firstElementChild,
    addImageButton = window.document.getElementsByClassName("deviantart-muro-add-comment-drawing")[0];

function openMuro() {
    loadingDiv.style.visibility = 'visible';
    savingDiv.style.visibility  = 'hidden';
    muroIframe.style.visibility = 'hidden';
    muroModalContainer.style.display = '';
    muroIframe.src = muroIframe.getAttribute('data-src');
}

function closeMuro() {
    muroModalContainer.style.display = 'none';
    muroIframe.src = '';
}

function receiveMessage(message) {
    // TODO: check against list of shortcode iframes.
    if (message.source !== muroIframe.contentWindow) {
        return; // Not from our iframe, ignore it.
    }

    switch (message.data.type) {
    case 'ready':
        muroIframe.style.visibility = 'visible';
        loadingDiv.style.visibility = 'hidden';
        return;
    case 'error':
        // TODO: handle errors
        alert(message.data.error);
        closeMuro();
        return;
    case 'cancel':
        closeMuro();
        return;
    case 'done':
        var image = message.data.image;

        savingDiv.style.visibility  = 'visible';
        muroIframe.style.visibility = 'hidden';
        imageStore.value = image.replace(/^data:image\/png;base64,/, '');
        previewImg.src = image;
        previewDiv.style.display = '';
        closeMuro();
        return;
    }
}

addImageButton.addEventListener('click', openMuro, false);
window.addEventListener("message", receiveMessage, false);

})(window);

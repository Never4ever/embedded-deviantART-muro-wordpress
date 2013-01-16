(function (window, $, wp, undefined) {
"use strict";

function reportError(error) {
    window.console.error(error);
    // TODO: error handling.
    // For now, silently ignore, yay, and bounce back to last state
    wp.media.editor.get().setState(wp.media.editor.get()._lastState);
}

function receiveMessage(message) {
    var iframe;

window.console.info("modal receive", message);
    if ((message.data.type !== 'damuro') ||
        !(iframe = $('.media-iframe').find('iframe').get(0)) ||
        (message.source !== iframe.contentWindow)) {
        return; // Not from our iframe, ignore it.
    }

    switch (message.data.event) {
    case 'upload':
        if (message.data.data.success !== true) {
            // TODO: extract error message
            reportError("Error saving deviantART muro image as WordPress attachment");
            return;
        }
        // datadotdatadotdata, try saying that after a few drinks.
        var attachment = wp.media.model.Attachment.create(message.data.data.data);
        // Probably a better way to achieve this, but this switches us back to the
        // insert media panel, sets us to browse mode, updates the library to reflect
        // the fact that the server has the attachment, and selects it so that the user
        // can now choose alignment/caption/etc options and insert into the post.
        wp.media.editor.get().setState('insert');
        wp.media.editor.get().content.mode('browse');
        wp.media.editor.get().state().get('library').add(attachment);
        wp.media.editor.get().state().get('selection').reset().add(attachment); // .single() doesn't work?
        return;
    case 'cancel':
        // Bounce back to last state
        wp.media.editor.get().setState(wp.media.editor.get()._lastState);
        return;
    case 'error':
        reportError(message.data.error);
        return;
    default:
        // For forwards-compat, silently ignore unknown events.
    }
}
window.addEventListener("message", receiveMessage, false);

})(window, jQuery, window.wp);

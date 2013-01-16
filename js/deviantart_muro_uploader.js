(function (window, undefined) {
"use strict";

/**
 * options = {
 *   url:              url to post to
 *   extra_params:     object describing additional non-file form fields to send
 *   files:            object describing fake-file form fields to send, see "files" below
 *   success:          callback to call on post success
 *   fail:             callback to call on post failure
 *   }
 * files = {
 *   <field_name>: {   name of the form file field to send the base64 data in
 *     base64data:     string of base64 encoded data you want to fake a file upload for
 *     filename:       name of the fake "local file" the base64 data pretends to come from
 *     content_type:   MIME content-type to send the data under
 *     }
 *   }
 */
function uploadBase64Data(options) {
    var xhr      = new window.XMLHttpRequest(),
        boundary = '------multipartformboundary' + (new Date()).getTime(),
        dashdash = '--',
        crlf     = '\r\n',
        content  = '',
        success  = options.success,
        fail     = options.fail,
        name;

    // Lifted from http://stackoverflow.com/questions/5292689/sending-images-from-canvas-elements-using-ajax-and-php-files
    if (xhr.sendAsBinary === undefined) {
        xhr.legacySend = xhr.send;
        xhr.sendAsBinary = function (string) {
            var bytes = Array.prototype.map.call(string, function (c) {
                    return c.charCodeAt(0) & 0xff;
                });
            this.legacySend(new window.Uint8Array(bytes).buffer);
        };
    }
    xhr.send = xhr.sendAsBinary;

    xhr.open("POST", options.url, true);
    xhr.setRequestHeader("Content-Type", "multipart/form-data; boundary=" + boundary);

    // options.extra_params contains our nonce and other details.
    for (name in options.extra_params) {
        if (options.extra_params.hasOwnProperty(name)) {
            content += dashdash + boundary + crlf +
                'Content-Disposition: form-data; name="' + name + '"' + crlf +
                crlf +
                options.extra_params[name] + crlf;
        }
    }

    for (name in options.files) {
        if (options.files.hasOwnProperty(name)) {
            var file = options.files[name];
            content += dashdash + boundary + crlf +
                'Content-Disposition: form-data; name="' + name +'"; filename="' + file.filename + '"' + crlf +
                'Content-Type: ' + file.content_type + crlf +
                crlf +
                window.atob(file.base64data) + crlf;
        }
    }

    content += dashdash + boundary + dashdash + crlf;

    xhr.setRequestHeader("Content-length", content.length);
    xhr.setRequestHeader("Connection", "close");
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            xhr.onreadystatechange = null;
            if (xhr.status === 200) {
                success.call(null, xhr);
            } else {
                fail.call(null, xhr);
            }
        }
    };
    // execute
    xhr.send(content);
}

window.deviantart_muro_uploader = uploadBase64Data;

})(window);

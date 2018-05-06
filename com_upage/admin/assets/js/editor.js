/*exported upageEditor */
/*global Utility, SessionTimeoutError*/

var UPageUtility = {};

(function() {
    'use strict';

    UPageUtility.downloadByteArrayInline = function downloadByteArrayInline(url, withCredentials, numberOfTries, callback) {

        var xhr = new window.XMLHttpRequest();

        function onError() {
            if (numberOfTries >= 2) {
                callback(UPageUtility.createError(url, xhr));
            } else {
                setTimeout(function () {
                    downloadByteArrayInline(url, withCredentials, numberOfTries + 1, callback);
                }, 50);
            }
        }

        xhr.withCredentials = !!withCredentials;
        xhr.open("GET", url, true);
        xhr.responseType = "arraybuffer";
        xhr.onload = function () {
            if (xhr.readyState !== 4 || xhr.status !== 200) {
                onError(xhr);
            } else {
                var array = xhr.response ? new window.Uint8Array(xhr.response) : new window.Uint8Array("");
                callback(null, array);
            }
        };
        xhr.onerror = onError;
        xhr.send();
    };

    UPageUtility.createError = function (url, xhr) {
        if (['0', '-1', 'session_error'].indexOf(xhr.responseText) !== -1) {
            return new SessionTimeoutError('SessionTimeoutError');
        }
        return Utility.createRequestError(url, xhr, xhr.status, 'Failed to send a request');
    };


    UPageUtility.showError = function showError(error) {
        if (!error) {
            return;
        }
        console.error(error);
    };


    UPageUtility.isBase64String = function isBase64String(str) {
        return typeof str === 'string' &&
            str.indexOf(';base64,') !== -1;
    };
})();

var DataUploader = (function() {
    'use strict';

    function savePage(postData, callback) {
        doRequest(window.upageSettings.actions.savePage, postData, callback);
    }

    function saveSiteSettings(data, callback) {
        doRequest(window.upageSettings.actions.saveSiteSettings, {settings: data}, callback);
    }

    function uploadImage(imageData, callback) {

        var mimeType = imageData.mimeType;
        var fileName = imageData.fileName || 'image.png';

        if (imageData.data instanceof Uint8Array || imageData.data instanceof File || imageData.data instanceof Blob) {
            upload(imageData.data);
            return;
        }

        if (UPageUtility.isBase64String(imageData.data)) {
            var parts = imageData.data.split(';base64,');
            mimeType = parts[0].split(':')[1];
            var raw = window.atob(parts[1]);
            var rawLength = raw.length;

            var uInt8Array = new Uint8Array(rawLength);

            for (var i = 0; i < rawLength; i++) {
                uInt8Array[i] = raw.charCodeAt(i);
            }
            upload(uInt8Array);
            return;
        }

        UPageUtility.downloadByteArrayInline(imageData.data, false, 1, function(error, array) {
            if (error) {
                callback(error);
                return;
            }
            upload(array);
        });

        /**
         * @param {Uint8Array|Blob|File} fileData
         */
        function upload(fileData) {
            var uploader = new ImageUploader(fileData, $.extend(true, {
                url: window.upageSettings.actions.uploadImage,
                type: mimeType,
                fileName: fileName,
                params: imageData.params
            }, window.upageSettings.uploadImageOptions), callback);
            uploader.upload();
        }
    }

    function getSite(callback) {
        doRequest(window.upageSettings.actions.getSite, {}, callback);
    }

    function getSitePosts(options, callback) {
        doRequest(window.upageSettings.actions.getSitePosts, {options: options}, callback);
    }

    function updateManifest(data, callback) {
        doRequest(window.upageSettings.actions.updateManifest, {data: data}, callback);
    }

    function doRequest(url, data, onError, onSuccess) {
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: $.extend(true, {}, window.upageSettings.ajaxData, data)
        }).done(function requestSuccess(response, status, xhr) {
            if (response.result === 'done') {
                if (typeof onSuccess === 'undefined') {
                    onError(null, response);
                } else {
                    onSuccess(response);
                }
                return;
            }
            onError(UPageUtility.createError(url, xhr));
        }).fail(function requestFail(xhr) {
            onError(UPageUtility.createError(url, xhr));
        });
    }

    function loggedInWrap(func) {
        var wrapped = function (data, callback) {
            func(data, function (error, response) {
                if (error instanceof SessionTimeoutError && typeof parent.dataBridge.doLoggedIn === 'function') {
                    parent.dataBridge.doLoggedIn(function (success) {
                        if (success) {
                            wrapped(data, callback);
                        } else {
                            callback(error, response);
                        }
                    });
                } else {
                    callback(error, response);
                }
            })
        };
        return wrapped;
    }

    return {
        savePage: loggedInWrap(savePage),
        saveSiteSettings: loggedInWrap(saveSiteSettings),
        uploadImage: loggedInWrap(uploadImage),
        getSite: loggedInWrap(getSite),
        getSitePosts: loggedInWrap(getSitePosts),
        updateManifest: loggedInWrap(updateManifest)
    };
})();

/**
 *
 * @param {Uint8Array|Blob|File} fileData
 * @param {object} options
 * @param {string} options.type MIME type
 * @param {string} options.formFileName
 * @param {string} options.fileName
 * @param callback
 * @constructor
 */
function ImageUploader(fileData, options, callback) {
    'use strict';

    var type = options.type || '';
    var file = new Blob([fileData], { type: type });

    this.upload = function upload() {

        setTimeout(function () {
            var formData = new FormData();
            formData.append(options.formFileName, file, options.fileName);

            var params = options.params;
            if (typeof params === 'object') {
                for (var i in params) {
                    if (params.hasOwnProperty(i)) {
                        formData.append(i, params[i]);
                    }
                }
            }

            return $.ajax({
                url: options.url,
                data: formData,
                type: 'POST',
                mimeType: 'application/octet-stream',
                processData: false,
                contentType: false,
                headers: {}
            }).done(function requestSuccess(response, status, xhr) {
                var result;
                try {
                    result = JSON.parse(response);
                } catch(e) {
                    callback(UPageUtility.createError(options.url, xhr));
                    return;
                }
                callback(null, result);
            }).fail(function requestFail(xhr) {
                callback(UPageUtility.createError(options.url, xhr));
            });

        }, 0);
    };
}

var upageEditor = (function () {
    'use strict';

    var upageEditor = {};
    window.upageSettings = parent.dataBridge.settings;
    window.cmsSettings = parent.dataBridge.cmsSettings;

    /**
     *
     * @param {object} saveData
     * @param callback
     */
    upageEditor.save = function save(saveData, callback) {
        if (saveData.id < 0 && window.upageSettings.pageId) {
            saveData.id = window.upageSettings.pageId;
        }
        if (saveData.id < 0 && window.upageSettings.startPageTitle) {
            saveData.title = window.upageSettings.startPageTitle;
        }

        DataUploader.savePage({
            id: saveData.id,
            data: {
                html: saveData.html,
                publishHtml: saveData.publishHtml,
                backlink: saveData.backlink,
                head: saveData.head,
                fonts: saveData.fonts,
                bodyClass: saveData.bodyClass
            },
            title: saveData.title,
            keywords: saveData.keywords,
            description: saveData.description,
            metaTags: saveData.metaTags,
            customHeadHtml: saveData.customHeadHtml,
            titleInBrowser: saveData.titleInBrowser,
            isPreview: saveData.isPreview
        }, function (error, response) {
            if (error) {
                callback(error);
                return;
            }
            window.upageSettings.pageId = response.data.id;
            callback(null, response);
        });
    };

    /**
     *
     * @param {File|Blob} file
     * @param {object}    options
     * @param {string}    options.pageId
     * @param {function}  callback
     */
    upageEditor.saveImage = function saveImage(file, options, callback) {
        DataUploader.uploadImage({
            mimeType: file.type,
            fileName: file.name,
            data: file,
            params: {pageId: options.pageId}
        }, callback);
    };

    upageEditor.getCloseUrl = function getCloseUrl(isNewPage) {
        if (isNewPage) {
            return window.upageSettings.dashboardUrl;
        } else {
            return window.upageSettings.editPostUrl.replace('{id}', window.upageSettings.pageId || window.upageSettings.startPageId);
        }
    };

    upageEditor.getLoginUrl = function getLoginUrl() {
        return window.upageSettings.loginUrl.replace(encodeURIComponent('{id}'), window.upageSettings.pageId || window.upageSettings.startPageId);
    };

    return upageEditor;
})();
<?php // no direct access

defined('_JEXEC') or die('Restricted access');

$settings = array(
    'manifestUrl' => ($domain ? $domain : '//nicepage.com') . '/Content/app.manifest',
    'updateManifestUrl' => $editorSettings['actions']['updateManifest']
);
?>
<!DOCTYPE html>
<html>
<head>
    <script>
        function sendRequest(data, callback) {
            var xhr = new XMLHttpRequest();

            function onError() {
                callback(new Error('Failed to send a request to ' + data.url + '\n' + JSON.stringify({
                        responseText: xhr.responseText,
                        readyState: xhr.readyState,
                        status: xhr.status
                    }, null, 4)));
            }

            xhr.onerror = onError;
            xhr.onload = function () {
                if (this.readyState === 4 && this.status === 200) {
                    callback(null, this.response);
                } else {
                    onError();
                }
            };
            xhr.open(data.method || 'GET', data.url);

            if (data.data) {
                var formData = new FormData();
                formData.append("manifest", data.data.manifest);
                formData.append("version", data.data.version);
                formData.append("domain", data.data.domain);
                xhr.send(formData);
            } else {
                xhr.send();
            }
        }

        function addVersionParam(url) {
            return url + (url.indexOf('?') === -1 ? '?' : '&') + 'rnd=' + (new Date()).getTime();
        }

        function onError(e) {
            console.error(e);
            alert('Failed to load manifest.');
        }

        var settings = <?php echo json_encode($settings); ?>;

        sendRequest({url: addVersionParam(settings.manifestUrl)}, function (error, response) {
            if (error) {
                onError(error);
                return;
            }

            var match = new RegExp('# uPageVer: (.*)', 'ig')['exec'](response);
            if (!match || !match[1]) {
                onError(new Error('Failed to parse manifest content:\n' + response));
                return;
            }

            var latestVer = match[1];
            sendRequest({
                url: settings.updateManifestUrl,
                method: 'POST',
                data: {
                    manifest: response,
                    version: latestVer,
                    domain: <?php echo json_encode($domain); ?>
                }
            }, function (error, response) {
                if (error) {
                    onError(error);
                    return;
                }
                var result = JSON.parse(response);
                if (!result) {
                    onError(new Error('Failed to parse CMS response:\n' + response));
                    return;
                }

                window.location.reload(true);
                /*if (window.location.href === result.startUrl) {
                 window.location.reload(true);
                 } else {
                 window.location.href = result.startUrl;
                 }*/
            });
        });
    </script>
</head>
<body>

</body>
</html>
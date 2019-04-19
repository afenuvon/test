
/*
* 
* ===================================================================
* CLOUD CHAT LOADING FILE
* ===================================================================
*
*/

if (typeof String.prototype.replaceAll === 'undefined') {
    String.prototype.replaceAll = function (match, replace) {
        return this.replace(new RegExp(match.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&'), 'g'), () => replace);
    }
}

(function () {
    let xhr = new XMLHttpRequest();
    let url_full = document.getElementById('chat-init').src;
    let url = url_full;
    let chat_id = url.substring(url.indexOf('id=') + 3);
    url = url.substring(0, url.indexOf('/account'));
    let url_cloud = url + '/account/init.php?id=' + chat_id;
    if ('withCredentials' in xhr) {
        xhr.open('GET', url_cloud, true);
    } else if (typeof XDomainRequest != 'undefined') {
        xhr = new XDomainRequest();
        xhr.open('GET', url_cloud);
    } else {
        return false;
    }
    xhr.onload = () => {
        if (xhr.responseText) {
            let response = JSON.parse(xhr.responseText);
            let url_sb = `${url}/script/js/${url.includes('localhost') || location.href.includes('localhost') || url.includes('debug') || location.href.includes('debug') ? 'main' : 'min/main.min'}.js?v=${response[1]}&cloud=${response[0]}${url_full.includes('?') ? ('&' + url_full.substring(url_full.indexOf('?') + 1)) : ''}`;
            if (typeof jQuery == 'undefined') {
                getScript(url + '/script/js/min/jquery.min.js', () => {
                    loadScript(url_sb, 'sbinit');
                });
            } else {
                loadScript(url_sb, 'sbinit');
            }
        }
    }
    xhr.send();

    function loadScript(url, id = '') {
        var script = document.createElement('script');
        script.src = url;
        script.id = id;
        document.body.appendChild(script);
    }

    function getScript(source, callback) {
        var script = document.createElement('script');
        var prior = document.getElementsByTagName('script')[0];
        script.async = 1;
        script.onload = script.onreadystatechange = function (_, isAbort) {
            if (isAbort || !script.readyState || /loaded|complete/.test(script.readyState)) {
                script.onload = script.onreadystatechange = null;
                script = undefined;
                if (!isAbort && callback) setTimeout(callback, 0);
            }
        };
        script.src = source;
        prior.parentNode.insertBefore(script, prior);
    }
}()); 
/**
 * os-cloudflare-ip settings UI
 */
$( document ).ready(function() {

    var generalData = {};

    // ---- general settings ----

    function loadGeneral() {
        AjaxApi.get('/api/cloudflareip/settings/getGeneral', {}, function(data) {
            generalData = data.general;
            $('#general\\.enabled').prop('checked', (generalData.enabled == '1'));
            $('#general\\.account_id').val(generalData.account_id);
            $('#general\\.api_token').val(generalData.api_token);
            $('#general\\.interval').val(generalData.interval);
        });
    }

    $('#saveGeneralBtn').click(function() {
        var data = {
            'general.enabled': $('#general\\.enabled').is(':checked') ? '1' : '0',
            'general.account_id': $('#general\\.account_id').val(),
            'general.api_token': $('#general\\.api_token').val(),
            'general.interval': $('#general\\.interval').val()
        };
        AjaxApi.post('/api/cloudflareip/settings/setGeneral', data, function(data) {
            if (data.status == 'ok') {
                $('#saveGeneralBtn').removeClass('btn-primary').addClass('btn-success').text('Saved');
                setTimeout(function(){ $('#saveGeneralBtn').removeClass('btn-success').addClass('btn-primary').text('Save'); }, 1500);
                loadGeneral();
            } else {
                BootstrapDialog.alert(data.message || 'Save failed');
            }
        });
    });

    // ---- WAN rows grid ----

    $('#grid-wans').UIBootgrid({
        search: '/api/cloudflareip/settings/searchWans',
        get: '/api/cloudflareip/settings/getWan/',
        set: '/api/cloudflareip/settings/setWan/',
        add: '/api/cloudflareip/settings/addWan/',
        del: '/api/cloudflareip/settings/delWan/',
        toggle: '/api/cloudflareip/settings/toggleWan/',
        sort: ['interface'],
        options: { rowCount: 20 }
    });

    $('#addWanBtn').click(function() {
        UIBootgrid.showDialog($('#grid-wans'), 'wan', $('#frm_wan'));
    });

    // ---- update now / status ----

    function loadStatus() {
        AjaxApi.get('/api/cloudflareip/settings/status', {}, function(data) {
            $('#statusPanel').text(JSON.stringify(data.state, null, 2));
        });
    }

    $('#updateNowBtn').click(function() {
        $('#updateNowBtn').prop('disabled', true).text('Updating...');
        AjaxApi.post('/api/cloudflareip/settings/update', {}, function(data) {
            $('#updateNowBtn').prop('disabled', false).text('Update now');
            $('#statusPanel').text(JSON.stringify(data, null, 2));
            loadStatus();
        });
    });

    $('#refreshStatusBtn').click(loadStatus);

    // ---- init ----
    loadGeneral();
    loadStatus();
});

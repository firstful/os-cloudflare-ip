#!/usr/local/bin/php
<?php
/**
 * CloudflareIp backend rotation script
 *
 * Reads the plugin config from /conf/config.xml, reads each configured WAN
 * interface's current IPv4 (and optionally global IPv6) address, and if
 * anything changed, replaces the Cloudflare named IP list contents.
 *
 * Output is captured by configd (type: script_output) and the state is
 * written to /var/run/cloudflareip.state for the UI status panel.
 */

$CF_API = 'https://api.cloudflare.com/client/v4/accounts';
$STATE_FILE = '/var/run/cloudflareip.state';

function logmsg($msg)
{
    fwrite(STDOUT, date('c') . ' ' . $msg . "\n");
}

function cf_request($method, $url, $token, $body = null)
{
    $ch = curl_init($url);
    $headers = array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    );
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ));
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== '') {
        throw new Exception("curl error: $err");
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['success'])) {
        throw new Exception("bad API response (HTTP $code): " . substr($raw, 0, 300));
    }
    return array($code, $data);
}

/**
 * Interface IPs: parse `ifconfig <iface>` for inet / global inet6
 */
function interface_ips($iface)
{
    $out = shell_exec('/sbin/ifconfig ' . escapeshellarg($iface) . ' 2>/dev/null');
    if ($out === null || $out === false || trim($out) === '') {
        return null; // interface does not exist
    }
    $ipv4 = null;
    $ipv6 = null;
    foreach (explode("\n", $out) as $line) {
        if (preg_match('/\binet\s+([0-9.]+)/', $line, $m)) {
            $ipv4 = $m[1];
        }
        if (preg_match('/\binet6\s+([0-9a-f:]+)%?[0-9]*/i', $line, $m)) {
            $addr = strtolower($m[1]);
            if (strpos($addr, 'fe80:') !== 0) { // skip link-local
                $ipv6 = $addr;
            }
        }
    }
    return array('ipv4' => $ipv4, 'ipv6' => $ipv6);
}

/**
 * Resolve (or create) a named IP list; returns the list id.
 */
function ensure_list($accountId, $token, $listName)
{
    global $CF_API;
    list($code, $data) = cf_request('GET', $CF_API . '/' . $accountId . '/rules/lists?per_page=100', $token);
    foreach (($data['result'] ?? array()) as $list) {
        if (($list['name'] ?? '') === $listName) {
            return $list['id'];
        }
    }
    list($code, $data) = cf_request('POST', $CF_API . '/' . $accountId . '/rules/lists', $token, array(
        'name' => $listName,
        'kind' => 'ip',
        'description' => 'Maintained by os-cloudflare-ip (OPNsense)',
    ));
    if (($data['success'] ?? false) !== true || !isset($data['result']['id'])) {
        $msg = $data['errors'][0]['message'] ?? 'unknown error';
        throw new Exception("cannot create list '$listName': $msg");
    }
    return $data['result']['id'];
}

/**
 * Replace all items of a list; polls the async bulk operation.
 */
function set_list_items($accountId, $token, $listId, array $items)
{
    global $CF_API;
    list($code, $data) = cf_request('PUT', $CF_API . '/' . $accountId . '/rules/lists/' . $listId . '/items', $token, $items);
    $opId = $data['result']['operation_id'] ?? null;
    if ($opId === null) {
        $msg = $data['errors'][0]['message'] ?? ('HTTP ' . $code);
        throw new Exception('bulk update failed: ' . $msg);
    }
    // poll until done (bulk ops are async; give it up to 30s)
    for ($i = 0; $i < 30; $i++) {
        sleep(1);
        list($code, $data) = cf_request('GET', $CF_API . '/' . $accountId . '/rules/lists/bulk_operations/' . $opId, $token);
        $status = $data['result']['status'] ?? 'unknown';
        if ($status === 'completed') {
            return true;
        }
        if ($status === 'failed') {
            throw new Exception('bulk operation failed: ' . json_encode($data['result']));
        }
    }
    throw new Exception('bulk operation timed out');
}

// ---- main ----

$state = array('last_run' => date('c'), 'wans' => array(), 'errors' => array());

try {
    $config = simplexml_load_file('/conf/config.xml');
    if ($config === false) {
        throw new Exception('cannot read /conf/config.xml');
    }
    $cf = $config->OPNsense->CloudflareIp ?? null;
    if ($cf === null) {
        throw new Exception('no cloudflareip config section');
    }
    $general = $cf->general;
    $enabled = (string)$general->enabled === '1';
    $accountId = trim((string)$general->account_id);
    $token = (string)$general->api_token;

    if (!$enabled) {
        $state['errors'][] = 'plugin disabled';
        file_put_contents($STATE_FILE, json_encode($state));
        logmsg('disabled, nothing to do');
        exit(0);
    }
    if ($accountId === '' || $token === '') {
        throw new Exception('account_id or api_token not configured');
    }

    // single target list holds the egress IPs of ALL configured WANs
    $listName = trim((string)$general->list_name);
    if ($listName === '') {
        throw new Exception('list_name not configured');
    }

    // resolve logical interface roles (wan/opt1) to physical names (vtnet0/...)
    $physByRole = array();
    foreach (($config->interfaces->children() ?? array()) as $role => $ifaceNode) {
        $physByRole[(string)$role] = trim((string)$ifaceNode->if);
    }

    // pass 1: collect current egress IPs from every enabled WAN row
    $items = array();
    $wanNodes = $cf->wans->wan ?? array();
    foreach ($wanNodes as $wan) {
        if ((string)$wan->enabled !== '1') {
            continue;
        }
        $iface = trim((string)$wan->interface);
        $includeV6 = (string)$wan->include_v6 === '1';

        $row = array('interface' => $iface, 'updated' => false);

        $phys = isset($physByRole[$iface]) ? $physByRole[$iface] : $iface;
        $ips = interface_ips($phys);
        if ($ips === null) {
            $state['errors'][] = "$iface: interface not found";
            continue;
        }
        if ($ips['ipv4'] === null && (!$includeV6 || $ips['ipv6'] === null)) {
            $state['errors'][] = "$iface: no usable address";
            continue;
        }
        if ($ips['ipv4'] !== null) {
            $items[] = array('ip' => $ips['ipv4']);
        }
        if ($includeV6 && $ips['ipv6'] !== null) {
            $items[] = array('ip' => $ips['ipv6']);
        }
        $row['ipv4'] = $ips['ipv4'];
        $row['ipv6'] = $ips['ipv6'];
        $state['wans'][] = $row;
    }

    if (count($items) === 0) {
        throw new Exception('no usable egress IPs from configured WANs');
    }

    // dedupe identical addresses (two WANs may NAT to the same public IP)
    $seen = array();
    foreach ($items as $item) {
        $seen[$item['ip']] = true;
    }
    $items = array();
    foreach (array_keys($seen) as $ip) {
        $items[] = array('ip' => $ip);
    }

    // cache last-known state (avoid useless API calls)
    $cacheFile = '/var/run/cloudflareip.' . md5($accountId . '/' . $listName) . '.cache';
    $lastItems = @json_decode(@file_get_contents($cacheFile), true);
    if ($lastItems === $items) {
        logmsg("$listName: unchanged (" . json_encode($items) . ')');
    } else {
        $listId = ensure_list($accountId, $token, $listName);
        set_list_items($accountId, $token, $listId, $items);
        file_put_contents($cacheFile, json_encode($items));
        logmsg("$listName: updated to " . json_encode($items));
        foreach ($state['wans'] as &$row) {
            $row['updated'] = true;
        }
        unset($row);
    }
} catch (Exception $e) {
    $state['errors'][] = $e->getMessage();
    logmsg('ERROR: ' . $e->getMessage());
}

file_put_contents($STATE_FILE, json_encode($state));

<?php
/**
 * CloudflareIp API controller
 *
 * Settings CRUD + "update now" + status
 */

namespace OPNsense\CloudflareIp\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\Backend;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'CloudflareIp';
    protected static $internalModelClass = '\OPNsense\CloudflareIp\CloudflareIp';

    /**
     * search WAN rows
     */
    public function searchWansAction()
    {
        return $this->searchBase('wans.wan', array('interface', 'list_name', 'include_v6', 'enabled', 'description'));
    }

    /**
     * get single WAN row
     */
    public function getWanAction($uuid = null)
    {
        return $this->getBase('wans.wan', 'wan', $uuid);
    }

    /**
     * add WAN row
     */
    public function addWanAction()
    {
        return $this->addBase('wans.wan', 'wan');
    }

    /**
     * update WAN row
     */
    public function setWanAction($uuid)
    {
        return $this->setBase('wans.wan', 'wan', $uuid);
    }

    /**
     * delete WAN row
     */
    public function delWanAction($uuid)
    {
        return $this->delBase('wans.wan', $uuid);
    }

    /**
     * toggle WAN row enabled
     */
    public function toggleWanAction($uuid, $enabled = null)
    {
        return $this->toggleBase('wans.wan', $uuid, 'enabled', $enabled);
    }

    /**
     * run the rotation now (configd action)
     */
    public function updateAction()
    {
        $backend = new Backend();
        try {
            $response = $backend->configdRun('cloudflareip update');
            return array('status' => 'ok', 'response' => $response);
        } catch (\Exception $e) {
            return array('status' => 'error', 'response' => $e->getMessage());
        }
    }

    /**
     * last-run status (read state file written by the backend script)
     */
    public function statusAction()
    {
        $stateFile = '/var/run/cloudflareip.state';
        $state = @json_decode(@file_get_contents($stateFile), true);
        if (!is_array($state)) {
            $state = array('last_run' => null, 'wans' => array(), 'errors' => array('no state yet'));
        }
        return array('status' => 'ok', 'state' => $state);
    }
}

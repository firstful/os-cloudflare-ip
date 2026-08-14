<?php
/**
 * CloudflareIp page controller
 */

namespace OPNsense\CloudflareIp;

use OPNsense\Base\IndexController;

class SettingsController extends IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/CloudflareIp/settings');
        $this->view->generalForm = $this->getForm('general');
        $this->view->wanForm = $this->getForm('wan');
    }
}

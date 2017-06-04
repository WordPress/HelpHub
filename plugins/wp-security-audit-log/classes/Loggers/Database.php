<?php

class WSAL_Loggers_Database extends WSAL_AbstractLogger
{

    public function __construct(WpSecurityAuditLog $plugin)
    {
        parent::__construct($plugin);
        $plugin->AddCleanupHook(array($this, 'CleanUp'));
    }

    public function Log($type, $data = array(), $date = null, $siteid = null, $migrated = false)
    {
        // is this a php alert, and if so, are we logging such alerts?
        if ($type < 0010 && !$this->plugin->settings->IsPhpErrorLoggingEnabled()) return;

        // create new occurrence
        $occ = new WSAL_Models_Occurrence();
        $occ->is_migrated = $migrated;
        $occ->created_on = $date;
        $occ->alert_id = $type;
        $occ->site_id = !is_null($siteid) ? $siteid
            : (function_exists('get_current_blog_id') ? get_current_blog_id() : 0);
        $occ->Save();

        // set up meta data
        $occ->SetMeta($data);

        // Inject for promoting the paid add-ons
        if ($type != 9999) {
            $this->AlertInject($occ);
        }
    }

    public function CleanUp()
    {
        $now = current_time('timestamp');
        $max_sdate = $this->plugin->settings->GetPruningDate();
        $max_count = $this->plugin->settings->GetPruningLimit();
        $is_date_e = $this->plugin->settings->IsPruningDateEnabled();
        $is_limt_e = $this->plugin->settings->IsPruningLimitEnabled();

        if (!$is_date_e && !$is_limt_e) {
            return;
        } // pruning disabled
        $occ = new WSAL_Models_Occurrence();
        $cnt_items = $occ->Count();

        // Check if there is something to delete
        if ($is_limt_e && ($cnt_items < $max_count)) {
            return;
        }

        $max_stamp = $now - (strtotime($max_sdate) - $now);
        $max_items = (int)max(($cnt_items - $max_count) + 1, 0);

        $query = new WSAL_Models_OccurrenceQuery();
        $query->addOrderBy("created_on", false);
        // TO DO Fixing data
        if ($is_date_e) $query->addCondition('created_on <= %s', intval($max_stamp));
        if ($is_limt_e) $query->setLimit($max_items);

        if (($max_items-1) == 0) return; // nothing to delete

        $result = $query->getAdapter()->GetSqlDelete($query);
        $deletedCount = $query->getAdapter()->Delete($query);

        if ($deletedCount == 0) return; // nothing to delete
        // keep track of what we're doing
        $this->plugin->alerts->Trigger(0003, array(
                'Message' => 'Running system cleanup.',
                'Query SQL' => $result['sql'],
                'Query Args' => $result['args'],
            ), true);

        // notify system
        do_action('wsal_prune', $deletedCount, vsprintf($result['sql'], $result['args']));
    }

    private function AlertInject($occurrence)
    {
        $count = $this->CheckPromoToShow();
        if ($count && $occurrence->getId() != 0) {
            if (($occurrence->getId() % $count) == 0) {
                $promoToSend = $this->GetPromoAlert();
                if (!empty($promoToSend)) {
                    $link = '<a href="'.$promoToSend['link'].'" target="_blank">Upgrade Now</a>';
                    $this->Log(9999, array(
                        'ClientIP' => '127.0.0.1',
                        'Username' => 'Plugin',
                        'PromoMessage' => sprintf($promoToSend['message'], $link),
                        'PromoName' => $promoToSend['name']
                    ));
                }
            }
        }
    }

    private function GetPromoAlert()
    {
        $lastPromoSentId = $this->plugin->GetGlobalOption('promo-send-id');
        $lastPromoSentId = empty($lastPromoSentId) ? 0 : $lastPromoSentId;
        $promoToSend = null;
        $aPromoAlerts = $this->GetActivePromoText();
        if (!empty($aPromoAlerts)) {
            $promoToSend = isset($aPromoAlerts[$lastPromoSentId]) ? $aPromoAlerts[$lastPromoSentId] : $aPromoAlerts[0];

            if ($lastPromoSentId < count($aPromoAlerts)-1) {
                $lastPromoSentId++;
            } else {
                $lastPromoSentId = 0;
            }
            $this->plugin->SetGlobalOption('promo-send-id', $lastPromoSentId);
        }
        return $promoToSend;
    }

    private function GetActivePromoText()
    {
        $aPromoAlerts = array();
        $aPromoAlerts[] = array(
            'name' => 'Upgrade to Premium',
            'message' => 'Add email alerts, see who is logged in, generate reports, add search and other functionality by upgrading to Premium for just $89. <strong>%s</strong>',
            'link' => 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=auditviewer&utm_medium=promoalert&utm_campaign=plugin'
        );
        $aPromoAlerts[] = array(
            'name' => 'Get 70% Discount When You Upgrade to Premium',
            'message' => 'Benefit from a discount of 70&percnt; upgrade to premium for just $89 and add <strong>Email Alerts</strong>, <strong>User Logins Management</strong>, <strong>Search</strong> and <strong>Reporting</strong> functionality to the plugin. <strong>%s</strong>',
            'link' => 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=auditviewer&utm_medium=promoalert&utm_campaign=plugin'
        );
        $aPromoAlerts[] = array(
            'name' => 'Add Email Alerts, Search, Generate Reports and See Who is Logged In',
            'message' => 'Upgrade to premium and extend the plugin’s features with email alerts, report generator, free-text based search and user logins and sessions management. Benefit from a 70&percnt; discount. Prices starts at just $89 <strong>%s</strong>',
            'link' => 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=auditviewer&utm_medium=promoalert&utm_campaign=plugin'
        );
        return $aPromoAlerts;
    }

    private function CheckPromoToShow()
    {
        $promoToShow = null;
        // Check: Email Add-On, Search Add-On, Reports Add-On, External DB Add-On, Manage Users Sessions Add-on
        if (!class_exists('WSAL_NP_Plugin')) {
            $promoToShow[] = true;
        }
        if (!class_exists('WSAL_SearchExtension')) {
            $promoToShow[] = true;
        }
        if (!class_exists('WSAL_Rep_Plugin')) {
            $promoToShow[] = true;
        }
        if (!class_exists('WSAL_Ext_Plugin')) {
            $promoToShow[] = true;
        }
        if (!class_exists('WSAL_User_Management_Plugin')) {
            $promoToShow[] = true;
        }

        if (empty($promoToShow)) {
            return null;
        }
        return (count($promoToShow) == 5) ? 80 : null;
    }
}

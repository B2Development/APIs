<?php

class Preferences
{
    private $BP;

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

    }


    public function get($which, $filter)
    {
        $preferences = $which == -1 ? $this->BP->get_preferences() : $this->BP->get_preferences($which, $filter);
        $nvp = $which == -1 ? array() : $this->BP->get_nvp_list('preferences', $which);

        $allPreferences = array();

        $data = $this->buildOutput($preferences, $nvp, $filter);
        $this->addGlobalPreferences($data);

        $allPreferences['data'][] = $data;

        return($allPreferences);
    }

    public function update($which, $inputArray){
        if ($which == null) {
            $data = "You must provide a username.";
        } else {
            if (isset($inputArray['ShowGroups']) ||
                isset($inputArray['show_resolution_warning']) ||
                isset($inputArray['application_theme']) ||
                isset($inputArray['show_release_notes']) ||
                isset($inputArray['catalog_filter'])) {
                // for compatibility with the legacy UI, use NVP for grouping
                // Use NVP for screen resolution
                $data = $this->BP->save_nvp_list('preferences', $which, $inputArray);
            } else {
                $data = $this->BP->save_preferences($which, $inputArray);
            }
        }

        return $data;
    }

    function buildOutput($data, $nvpData, $filter){
        $dashActiveJobRefresh = isset($data['dash_active_job_refresh']) ? $data['dash_active_job_refresh'] : null;
        $dashAlertRefresh = isset($data['dash_alert_refresh']) ? $data['dash_alert_refresh'] : null;
        $dashBackupRefresh = isset($data['dash_backup_refresh']) ? $data['dash_backup_refresh'] : null;
        $dashReplicationRefresh = isset($data['dash_replication_refresh']) ? $data['dash_replication_refresh'] : null;
        $dashRestoreRefresh = isset($data['dash_restore_refresh']) ? $data['dash_restore_refresh'] : null;
        $dashStorageRefresh = isset($data['dash_storage_refresh']) ? $data['dash_storage_refresh'] : null;
        $pageJobsRefresh = isset($data['page_jobs_refresh']) ? $data['page_jobs_refresh'] : null;
        $pageProtectRefresh = isset($data['page_protect_refresh']) ? $data['page_protect_refresh'] : null;
        $pageRestore = isset($data['page_restore_refresh']) ? $data['page_restore_refresh'] : null;
        $language = isset($data['language']) ? $data['language'] : null;
        $setupWizard = isset($data['show_setup_wizard']) ? $data['show_setup_wizard'] : null;
        $helpOverlay = isset($data['show_help_overlay']) ? $data['show_help_overlay'] : null;
        $showEula = isset($data['show_eula']) ? $data['show_eula'] : null;
        $dashboardSettings = isset($data['dashboard_settings']) ? $data['dashboard_settings'] : "";
        $forumPostsRefresh = isset($data['dash_forum_posts_refresh']) ? $data['dash_forum_posts_refresh'] : null;
        $dailyFeedRefresh = isset($data['dash_daily_feed_refresh']) ? $data['dash_daily_feed_refresh'] : null;
        $recoveryAssuranceRefresh = isset($data['dash_recovery_assurance_refresh']) ? $data['dash_recovery_assurance_refresh'] : null;

        if ($filter){
            $data = array (
                $filter => $data[$filter]

            );
        } else {
            $data = array(
                'dash_active_job_refresh' => $dashActiveJobRefresh,
                'dash_alert_refresh' => $dashAlertRefresh,
                'dash_backup_refresh' => $dashBackupRefresh,
                'dash_replication_refresh' => $dashReplicationRefresh,
                'dash_restore_refresh' => $dashRestoreRefresh,
                'dash_storage_refresh' => $dashStorageRefresh,
                'page_jobs_refresh' => $pageJobsRefresh,
                'page_protect_refresh' => $pageProtectRefresh,
                'page_restore_refresh' => $pageRestore,
                'language' => $language,
                'show_setup_wizard' => $setupWizard,
                'show_help_overlay' => $helpOverlay,
                'show_eula' => $showEula,
                'dashboard_settings' => $dashboardSettings,
                'dash_forum_posts_refresh' => $forumPostsRefresh,
                'dash_daily_feed_refresh' => $dailyFeedRefresh,
                'dash_recovery_assurance_refresh' => $recoveryAssuranceRefresh
            );

            // Use nvp API for legacy UI compatibility, adding values as needed.
            if (isset($nvpData['ShowGroups'])) {
                $data['ShowGroups'] = $nvpData['ShowGroups'];
            }
            // Use nvp API for screen resolution warning.
            if (isset($nvpData['show_resolution_warning'])) {
                $data['show_resolution_warning'] = $nvpData['show_resolution_warning'];
            }
            // Use nvp API for application theme.
            if (isset($nvpData['application_theme'])) {
                $data['application_theme'] = $nvpData['application_theme'];
            }
            // Use nvp API for release notes
            if (isset($nvpData['show_release_notes'])) {
                $data['show_release_notes'] = $nvpData['show_release_notes'];
            }
            // Use nvp API for catalog filter
            if (isset($nvpData['catalog_filter'])) {
                $data['catalog_filter'] = $nvpData['catalog_filter'];
            }
        }

        return $data;
    }

    /*
     * Add selected global configuration values from the master.ini.
     * (Can be expanded as needed).
     */
    private function addGlobalPreferences(&$data)
    {
        if (is_array($data)) {
            $section = $this->BP->get_ini_section("CMC");
            if ($section !== false) {
                foreach ($section as $item) {
                    $field = strtolower($item['field']);
                    switch ($field) {
                        case 'timeout':     // Idle timeout
                        case 'loglevel':    // Log level
                            $data[$field] = $item['value'];
                            break;
                    }
                }
            }
        }
    }

}

?>

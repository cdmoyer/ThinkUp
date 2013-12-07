<?php
/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/model/class.InsightsGeneratorPlugin.php
 *
 * Copyright (c) 2012-2013 Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkup.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * Insights Generator Plugin
 *
 * Pluggable plugin (recursion!) for generating items in the insights stream.
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2012-2013 Gina Trapani
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 */

class InsightsGeneratorPlugin extends Plugin implements CrawlerPlugin {

    /*
     * @const The day on which we send weekly digests.
     */
    const WEEKLY_DIGEST_DAY_OF_WEEK = 1; // Monday

    /*
     * @var Current Unix timestamp, here for testing.
     */
    var $current_timestamp;

    public function __construct($vals=null) {
        parent::__construct($vals);
        $this->folder_name = 'insightsgenerator';
        $this->current_timestamp = time();
    }

    public function activate() {

    }

    public function deactivate() {

    }

    public function renderConfiguration($owner) {
        $controller = new InsightsGeneratorPluginConfigurationController($owner);
        return $controller->go();
    }

    public function renderInstanceConfiguration($owner, $instance_username, $instance_network) {
        return '';
    }

    public function crawl() {
        $logger = Logger::getInstance();

        // Include all the insights files so they register themselves
        foreach (glob(THINKUP_WEBAPP_PATH."plugins/insightsgenerator/insights/*.php") as $filename) {
            require_once $filename;
        }

        //Get instances by owner
        $instance_dao = DAOFactory::getDAO('InstanceDAO');
        $owner_dao = DAOFactory::getDAO('OwnerDAO');
        $current_owner = $owner_dao->getByEmail(Session::getLoggedInUser());

        $instances = $instance_dao->getByOwner($current_owner, false, true);

        // Get posts for last 7 days
        $number_days = 7;
        $post_dao = DAOFactory::getDAO('PostDAO');

        $insights_plugin_registrar = PluginRegistrarInsights::getInstance();

        foreach ($instances as $instance) {
            $last_week_of_posts = $post_dao->getAllPostsByUsernameOrderedBy($instance->network_username,
            $network=$instance->network, $count=0, $order_by="pub_date", $in_last_x_days = $number_days,
            $iterator = false, $is_public = false);
            $insights_plugin_registrar->runRegisteredPluginsInsightGeneration($instance, $last_week_of_posts,
            $number_days);
            $logger->logUserSuccess("Completed insight generation for ".$instance->network_username." on ".
            $instance->network, __METHOD__.','.__LINE__);
        }

        // Send Email digets the first run after 4am
        if (date('H', $this->current_timestamp) > 4) {
            $plugin_option_dao = DAOFactory::GetDAO('PluginOptionDAO');
            $options = $plugin_option_dao->getOptionsHash($this->folder_name, true);
            $plugin_dao = DAOFactory::getDAO('PluginDAO');
            $plugin_id = $plugin_dao->getPluginId($this->folder_name);
            $today = date('Y-m-d', $this->current_timestamp);

            $last_daily = isset($options['last_daily_email']) ? $options['last_daily_email']->option_value : null;
            if ($last_daily != $today && in_array($current_owner->notification_frequency, array('daily','both'))) {
                if ($last_daily === null) {
                    $plugin_option_dao->insertOption($plugin_id, 'last_daily_email', $today);
                } else {
                    $plugin_option_dao->updateOption($plugin_id, 'last_daily_email', $today);
                }
                $this->sendDailyDigest($current_owner);
            }

            $last_weekly = isset($options['last_weekly_email']) ? $options['last_weekly_email']->option_value : null;
            if ($last_weekly != $today && date('w', $this->current_timestamp) == self::WEEKLY_DIGEST_DAY_OF_WEEK
            && in_array($current_owner->notification_frequency, array('weekly','both'))) {
                if ($last_weekly === null) {
                    $plugin_option_dao->insertOption($plugin_id, 'last_weekly_email', $today);
                } else {
                    $plugin_option_dao->updateOption($plugin_id, 'last_weekly_email', $today);
                }
                $this->sendWeeklyDigest($current_owner);
            }
        }
    }

    /*
     * Send out Daily Digests
     * @param object $owner Owner to send for
     * return boolean Whether email was sent
     */
    private function sendDailyDigest($owner) {
        return $this->sendDigestSinceWithTemplate($owner, 'Yesterday 4am', '_email.daily_insight_digest.tpl');
    }

    /*
     * Send out Weekly Digests
     * @param object $owner Owner to send for
     * return boolean Whether email was sent
     */
    private function sendWeeklyDigest($owner) {
        return $this->sendDigestSinceWithTemplate($owner, '1 week ago 4am', '_email.weekly_insight_digest.tpl');
    }

    /*
     * Send out Insight Digest for a give time period
     * @param object $owner Owner to send for
     * @param string $start When to start insight lookup
     * @param string $template Email view temlate to use
     * return boolean Whether email was sent
     */
    private function sendDigestSinceWithTemplate($owner, $start, $template) {
        $insights_dao = DAOFactory::GetDAO('InsightDAO');
        $start_time = date( 'Y-m-d H:i:s', strtotime($start, $this->current_timestamp));
        $insights = $insights_dao->getAllOwnerInstanceInsightsSince($owner->id, $start_time);
        if (count($insights) == 0) {
            return false;
        }

        $config = Config::getInstance();
        $es = new ViewManager();
        $es->caching=false;

        $es->assign('apptitle', $config->getValue('app_title_prefix')."ThinkUp" );
        $es->assign('application_url', Utils::getApplicationURL());
        $es->assign('insights', $insights);
        $message = $es->fetch(Utils::getPluginViewDirectory($this->folder_name).$template);
        list ($subject, $message) = explode("\n", $message, 2);
        
        Mailer::mail($owner->email, $subject, $message);
        return true;
    }

}

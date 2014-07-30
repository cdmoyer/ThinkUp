<?php
/*
 Plugin Name: Location Sharing Awareness
 Description: How often you share your location?
 When: Weekly, Fridays for Twitter, Wednesday for Facebook
       Monthly, 26th for Facebook, 28th for Twitter
 */
/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/insights/locationawareness.php
 *
 * Copyright (c) 2014 Chris Moyer
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
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014 Chris Moyer
 * @author Chris Moyer <chris [at] inarow [dot] net>
 */
class LocationAwarenessInsight extends InsightPluginParent implements InsightPlugin {
    /**
     * Slug for this insight
     **/
    var $slug = 'locationawareness';

    public function generateInsight(Instance $instance, User $user, $last_week_of_posts, $number_days) {
        parent::generateInsight($instance, $user, $last_week_of_posts, $number_days);
        $this->logger->logInfo("Begin generating insight", __METHOD__.','.__LINE__);
        $monthly = 0;
        $weekly = 0;
        if ($instance->network == 'twitter') {
            $weekly = 6;
            $monthly = 28;
        } else if ($instance->network == 'facebook') {
            $weekly = 4;
            $monthly = 26;
        } else if ($instance->network == 'test_no_monthly') {
            $monthly = 0;
            $weekly = 2;
        }


        $did_monthly = false;
        if ($monthly && self::shouldGenerateMonthlyInsight($this->slug, $instance, 'today', false, $monthly)) {
            $post_dao = DAOFactory::getDAO('PostDAO');
            $posts = $post_dao->getAllPostsByUsernameOrderedBy($instance->network_username, $instance->network,
                $count=0, $order_by="pub_date", $in_last_x_days = date('t'),
                $iterator = false, $is_public = false);
            $this->generateMonthlyInsight($instance, $posts);
            $did_monthly = true;
        }

        $do_weekly = $weekly && !$did_monthly;
        if ($do_weekly && self::shouldGenerateWeeklyInsight($this->slug, $instance, 'today', false, $weekly)) {
            $this->generateWeeklyInsight($instance, $last_week_of_posts);
        }

        $this->logger->logInfo("Done generating insight", __METHOD__.','.__LINE__);
    }

    public function generateWeeklyInsight($instance, $posts) {
        $located_posts = 0;
        $located_days = array();
        $geo_data = array();
        foreach ($posts as $p) {
            if ($this->isPreciselyLocated($p)) {
                $geo_data[] = $p->geo;
                $located_posts++;
                $located_days[date('Y-m-d', strtotime($p->pub_date))] = 1;
            }
        }

        if ($located_posts < 5) {
            return;
        }


        // 45 minutes per posting
        $time = TimeHelper::secondsToGeneralTime($located_posts * 45 * 60);

        $insight = new Insight();
        $insight->slug = $this->slug;
        $insight->instance_id = $instance->id;
        $insight->date = $this->insight_date;
        $insight->related_data = array('map_points' => array_unique($geo_data));
        $insight->headline = $this->getHeadline($located_posts, 'week');
        $days = count($located_days);
        $insight->text = "Last week, ".$this->username." attached a precise location on ".$days." day".($days==1?'':'s')
            . " to a total of $located_posts ".$this->terms->getNoun('post', InsightTerms::PLURAL)
            . ". That's roughly $time during which ".$this->username." could be found in person.";
        $insight->filename = basename(__FILE__, ".php");
        $insight->setHeroImage(array(
                'url' => 'https://www.thinkup.com/assets/images/insights/2014-07/pushpin.jpg',
                'alt_text' => 'You are here.',
                'credit' => 'Photo: Appie Verschoor',
                'img_link' => 'https://www.flickr.com/photos/xiffy/6768438411'
        ));
        $this->insight_dao->insertInsight($insight);
    }

    public function generateMonthlyInsight($instance, $posts) {
        $located_posts = 0;
        $located_days = array();
        $geo_data = array();
        foreach ($posts as $p) {
            if ($this->isPreciselyLocated($p)) {
                $geo_data[] = $p->geo;
                $located_posts++;
                $located_days[date('Y-m-d', strtotime($p->pub_date))] = 1;
            }
        }

        if ($located_posts < 1) {
            return;
        }

        // 45 minutes per posting
        $time = TimeHelper::secondsToGeneralTime($located_posts * 45 * 60);

        $insight = new Insight();
        $insight->slug = $this->slug;
        $insight->instance_id = $instance->id;
        $insight->date = $this->insight_date;
        $insight->related_data = array('map_points' => array_unique($geo_data));
        $insight->headline = $this->getHeadline($located_posts, 'month');
        $insight->text = "Last month, ".$this->username." attached a precise location to $located_posts "
            . $this->terms->getNoun('post', $located_posts == 1 ? InsightTerms::SINGULAR : InsightTerms::PLURAL)
            . ". That's roughly $time during which ".$this->username." could be found in person.";
        $insight->setHeroImage(array(
                'url' => 'https://www.thinkup.com/assets/images/insights/2014-07/pushpin.jpg',
                'alt_text' => 'You are here.',
                'credit' => 'Photo: Appie Verschoor',
                'img_link' => 'https://www.flickr.com/photos/xiffy/6768438411'
        ));
        $insight->filename = basename(__FILE__, ".php");
        $this->insight_dao->insertInsight($insight);
    }

    public function getHeadline($total, $period) {
        return $this->getVariableCopy(array(
            "Where in the world is %username?",
            "%username's specific location was visible %total %times last %period.",
            "Tracking %username IRL.",
            "Every step %username takes.",
        ), array(
            'times' => $total == 1 ? 'time' : 'times',
            'total' => $total,
            'period' => $period
        ));
    }

    public function isPreciselyLocated($post) {
        if (empty($post->geo)) {
            return false;
        }

        if (strstr($post->geo, ',') === false) {
            return false;
        }

        $latlon = explode(',', $post->geo);

        $max_decimals = 0;
        for ($i=0; $i<2; $i++) {
            $val = $latlon[$i];
            $val = preg_replace('/^[-0-9]*\./', '', $val);
            $max_decimals = max(strlen($val), $max_decimals);
        }

        return $max_decimals > 7;
    }
}

$insights_plugin_registrar = PluginRegistrarInsights::getInstance();
$insights_plugin_registrar->registerInsightPlugin('LocationAwarenessInsight');

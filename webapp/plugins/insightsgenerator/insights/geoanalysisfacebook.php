<?php
/*
 * Plugin Name: Geographical Analysis
 * Description: Location of people who have made your post the most popular today.
 * When: Saturdays
 */
/**
 *
 *
 * ThinkUp/webapp/plugins/insightsgenerator/insights/geoanalysisfacebook.php
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
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp. If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * GeoAnalysisFacebook
 *
 * Copyright (c) 2014 Anna Shkerina
 *
 * @author Anna Shkerina blond00792@gmail.com
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014 Anna Shkerina
 */
class GeoAnalysisFacebookInsight extends InsightPluginParent implements InsightPlugin {
    public function generateInsight(Instance $instance, User $user, $last_week_of_posts, $number_days) {
        parent::generateInsight($instance, $last_week_of_posts, $number_days);
        $this->logger->logInfo("Begin generating insight", __METHOD__ . ',' . __LINE__);
        if ($instance->network == 'facebook'
            && self::shouldGenerateInsight('geo_analysis_facebook', $instance, $insight_date = 'today',
                $regenerate_existing_insight = true, $day_of_week = 5, count ($last_week_of_posts))) {

            $insight_baseline_dao = DAOFactory::getDAO ( 'InsightBaselineDAO' );
            $fpost_dao = DAOFactory::getDAO('FavoritePostDAO');
            $raw_geo = array ();
            foreach ($last_week_of_posts as $post) {
                $locations_fav = $fpost_dao->getLocationOfFavoriters($post->post_id);
                $locations_comm = $fpost_dao->getLocationOfCommenters($post->post_id);
                $geos = array_merge($locations_comm, $locations_fav);
                // extracting name of city from location
                foreach ($geos as $geo) {
                    $pos = strpos($geo ['location'], ",");
                    if ($pos == 0) {
                        $city = $geo['location'];
                    } else {
                        $city = substr($geo['location'], 0, $pos);
                    }
                    $raw_geo[] = array("name" => $geo['name'], "city" => $city);
                }
            }

            if (count($raw_geo)) {
                $cities = array();
                $unique_people = array();
                foreach ($raw_geo as $geo) {
                    if (!isset($cities[$geo['city']])) {
                        $cities[$geo['city']] = array();
                    }
                    if (!in_array($geo['name'], $cities[$geo['city']])) {
                        $cities[$geo['city']][] = $geo['name'];
                    }
                    if (!in_array($geo['name'], $unique_people)) {
                        $unique_people[] = $geo['name'];
                    }
                }

                $geo_data = array();
                foreach ($cities as $name => $people) {
                    if (count($people) == 1) {
                        $geo_data[] = array('city'=>$name, 'name'=>$people[0]);
                    }
                    else if (count($people) == 2) {
                        $geo_data[] = array('city'=>$name, 'name'=>$people[0] .' and '.$people[1]);
                    }
                    else if (count($people)>5) {
                        $total = count($people);
                        $people = array_slice($people, 0, 5);
                        $str = join(', ', $people).', and '.($total-5).' more';
                        $geo_data[] = array('city'=>$name, 'name'=>$people[0] .' and '.$people[1]);
                    }
                    else {
                        $str = join(', ', array_slice($people, 0, 4)).', and '.$people[4];
                        $geo_data[] = array('city'=>$name, 'name'=>$people[0] .' and '.$people[1]);
                    }
                }

                $insight = new Insight();
                $insight->slug = 'geo_analysis_facebook';
                $insight->instance_id = $instance->id;
                $insight->date = $this->insight_date;
                $insight->filename = basename(__FILE__, ".php");
                $insight->emphasis = Insight::EMPHASIS_MED;
                $insight->headline = "All over the world";
                $insight->text =  "<strong>" . number_format(count($unique_people)) . " people</strong> interested in "
                    . $instance->network_username . "'s posts last week";
                $insight->related_data = array('geo_data' => $geo_data);
                $this->insight_dao->insertInsight($insight);
            }
            $this->logger->logInfo ( "Done generating insight", __METHOD__ . ',' . __LINE__ );
        }
    }
}

$insights_plugin_registrar = PluginRegistrarInsights::getInstance();
$insights_plugin_registrar->registerInsightPlugin('GeoAnalysisFacebookInsight');

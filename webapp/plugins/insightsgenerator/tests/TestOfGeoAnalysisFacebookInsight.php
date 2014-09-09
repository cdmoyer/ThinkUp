<?php
/**
 *
 * webapp/plugins/insightsgenerator/tests/TestOfGeoAnalysisFacebookInsight.php
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
 * TestOfGeoAnalysisFacebookInsight
 *
 * Copyright (c) 2014 Anna Shkerina
 *
 * @author Anna Shkerina blond00792@gmail.com
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014 Anna Shkerina
 */

require_once dirname(__FILE__) . '/../../../../tests/init.tests.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/autorun.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/web_tester.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/model/class.InsightPluginParent.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/insights/geoanalysisfacebook.php';

class TestOfGeoAnalysisFacebookInsight extends ThinkUpInsightUnitTestCase {

    public function setUp() {
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function testGeoAnalysisFacebook() {
        // Get data ready that insight requires
        $builders = self::buildData();

        $instance = new Instance();
        $instance->id = 220;
        $instance->network_user_id = 19654321;
        $instance->network_username = 'user';
        $instance->network = 'facebook';

        $posts = array();
        $posts[] = new Post(array(
                'id'=>1333,
                'post_id'=>1333,
                'author_user_id'=>19654321,
                'author_username'=>'Testy One',
                'author_fullname'=>'User',
                'network'=>'facebook',
                'post_text'=>'This is a simple post 1.',
                'pub_date'=>date('Y-m-d H:i:s'),
                'reply_count_cache'=>1,
                'is_protected'=>0,
                'favlike_count_cache' => 1
        ));
        $posts[] = new Post(array(
                'id'=>2333,
                'post_id'=>2333,
                'author_user_id'=>19654321,
                'author_username'=>'Bob Boyer',
                'author_fullname'=>'User',
                'network'=>'facebook',
                'post_text'=>'This is a simple post 2.',
                'pub_date'=>date('Y-m-d H:i:s', strtotime('-1 day')),
                'reply_count_cache'=>1,
                'is_protected'=>0,
                'favlike_count_cache' => 1
        ));
        $insight_plugin = new GeoAnalysisFacebookInsight();
        $insight_plugin->generateInsight($instance, null, $posts, 5);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('geo_analysis_facebook', 220, $today);
        $data = unserialize($result->related_data);
        $this->assertNotNull($data['geo_data']);
        $geo_data = $data['geo_data'];
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertEqual($result->headline, 'All over the world');
        $this->assertIsA($geo_data, "array");
        $this->assertEqual(count($geo_data), 3);
        $this->assertEqual($geo_data[0]['city'], 'Kharkov');
        $this->assertEqual($geo_data[1]['city'], 'Buffalo');

        $result->id = 42;
        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    private function buildData() {
        $builders = array();

        $builders[] = FixtureBuilder::build('posts', array('id'=>1334, 'post_id'=>1334, 'author_user_id'=>19654321,
                'author_username'=>'Testy One', 'author_fullname'=>'User', 'author_avatar'=>'avatar.jpg',
                'network'=>'facebook', 'post_text'=>'This is a simple comment 1.',
                'pub_date'=>$now, 'reply_count_cache'=>0, 'is_protected'=>0,'favlike_count_cache' =>0,
                'in_reply_to_post_id' => 1333));

        $builders[] = FixtureBuilder::build('favorites', array('post_id'=>1333, 'author_user_id'=>19654321,
                'fav_of_user_id'=>19654320, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('users', array('user_id'=>19654321, 'user_name'=>'Testy One',
                'full_name'=>'User', 'gender'=>'male', 'location'=> 'Kharkov, Ukraine', 'avatar'=>'avatar.jpg',
                'is_protected'=>0, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('users', array('user_id'=>19654320, 'user_name'=>'Testy Two',
                'full_name'=>'User1', 'gender'=>'female', 'location'=> 'Chernigiv', 'avatar'=>'avatar.jpg',
                'is_protected'=>0, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('posts', array('id'=>2334, 'post_id'=>2334, 'author_user_id'=>29654321,
                'author_username'=>'user', 'author_fullname'=>'User', 'author_avatar'=>'avatar.jpg',
                'network'=>'facebook', 'post_text'=>'This is a simple comment 2.',
                'pub_date'=>$yesterday, 'reply_count_cache'=>0, 'is_protected'=>0,'favlike_count_cache' =>0,
                'in_reply_to_post_id' => 2333));

        $builders[] = FixtureBuilder::build('favorites', array('post_id'=>2333, 'author_user_id'=>19654321,
                'fav_of_user_id'=>29654321, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('users', array('user_id'=>29654321, 'user_name'=>'Testy One',
                'full_name'=>'User4', 'gender'=>'male', 'location'=> 'Chernigiv, Chernihivs\'Ka Oblast\', Ukraine',
                 'avatar'=>'avatar.jpg', 'is_protected'=>0, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('favorites', array('post_id'=>1333, 'author_user_id'=>19654321,
                'fav_of_user_id'=>111, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('users', array('user_id'=>111, 'user_name'=>'Testy Three',
                'full_name'=>'User', 'gender'=>'male', 'location'=> 'Buffalo, NY, USA', 'avatar'=>'avatar.jpg',
                'is_protected'=>0, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('favorites', array('post_id'=>1333, 'author_user_id'=>19654321,
                'fav_of_user_id'=>112, 'network'=>'facebook'));

        $builders[] = FixtureBuilder::build('users', array('user_id'=>112, 'user_name'=>'Testy Four',
                'full_name'=>'User', 'gender'=>'male', 'location'=> 'Buffalo, NY', 'avatar'=>'avatar.jpg',
                'is_protected'=>0, 'network'=>'facebook'));

        return $builders;

    }
}

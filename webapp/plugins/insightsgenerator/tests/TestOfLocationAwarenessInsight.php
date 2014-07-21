<?php
/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/tests/TestOfLocationAwarenessInsight.php
 *
 * Copyright (c) Chris Moyer
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
 * Test of LOL Count Insight
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014 Chris Moyer
 * @author Chris Moyer <chris[at]inarow[dot]net>
 */

require_once dirname(__FILE__) . '/../../../../tests/init.tests.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/autorun.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/web_tester.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/model/class.InsightPluginParent.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/insights/locationawareness.php';

class TestOfLocationAwarenessInsight extends ThinkUpInsightUnitTestCase {
    public function setUp(){
        parent::setUp();

        $instance = new Instance();
        $instance->id = 1;
        $instance->network_user_id = 42;
        $instance->network_username = 'supermayor';
        $instance->network = 'twitter';
        $this->instance = $instance;

        $this->insight_dao = DAOFactory::getDAO('InsightDAO');

        TimeHelper::setTime(2); // Force one headline for most tests
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function testConstructor() {
        $insight_plugin = new LocationAwarenessInsight();
        $this->assertIsA($insight_plugin, 'LocationAwarenessInsight' );
    }

    public function testWeeklySomePosts() {
        $this->instance->network = 'test_no_monthly';
        $builders = array();
        for ($i=0; $i<6; $i++) {
            $builders[] = $this->generatePost(true, $i);
            $builders[] = $this->generatePost(false, $i);
        }

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);
        $insight_plugin->generateInsight($this->instance, null, $posts, 3);
        $today = date('Y-m-d');
        $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
        $this->assertNotNull($result);
        $this->assertEqual($result->headline, "Tracking supermayor IRL.");
        $this->assertEqual($result->text, "Last week, supermayor attached a precise location on 5 days to a total of "
            . "5 posts. That's roughly 3 hours during which supermayor could be found in person.");

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testMapPoints() {
        $builders = array();
        $builders[] = FixtureBuilder::build('posts', array( 'geo' => '42.886927111,-78.877383111',
            'network' => $this->instance->network, 'author_username' => $this->instance->network_username,
            'post_text' => 'Hello Buffalo', 'pub_date' => '-2d'));
        $builders[] = FixtureBuilder::build('posts', array( 'geo' => '34.425323111,-103.191544111',
            'network' => $this->instance->network, 'author_username' => $this->instance->network_username,
            'post_text' => 'I am at the mall of America', 'pub_date' => '-2d'));
        $builders[] = FixtureBuilder::build('posts', array( 'geo' => '34.425323111,-103.191544111',
            'network' => $this->instance->network, 'author_username' => $this->instance->network_username,
            'post_text' => 'Back at the mall', 'pub_date' => '-2d'));
        $builders[] = FixtureBuilder::build('posts', array( 'geo' => '34.425323111,-103.191544111',
            'network' => $this->instance->network, 'author_username' => $this->instance->network_username,
            'post_text' => 'Still here.', 'pub_date' => '-2d'));
        $builders[] = FixtureBuilder::build('posts', array( 'geo' => '34.425323111,-103.191544111',
            'network' => $this->instance->network, 'author_username' => $this->instance->network_username,
            'post_text' => 'I love shopping', 'pub_date' => '-2d'));
        $builders[] = FixtureBuilder::build('posts', array( 'geo' => '34.425323111,-103.191544111',
            'network' => $this->instance->network, 'author_username' => $this->instance->network_username,
            'post_text' => 'Shopping is my life', 'pub_date' => '-2d'));

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);
        $insight_plugin->generateInsight($this->instance, null, $posts, 3);
        $today = date('Y-m-d');
        $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
        $this->assertNotNull($result);
        $this->assertEqual($result->headline, "Tracking @supermayor IRL.");
        $this->assertEqual($result->text, "Last month, @supermayor attached a precise location to "
            . "6 tweets. That's roughly 4 hours during which @supermayor could be found in person.");

        $data = unserialize($result->related_data);
        $this->assertIsA($data, 'Array');
        $this->assertIsA($data['map_points'], 'Array');
        $this->assertEqual(count($data['map_points']), 2);
        $this->assertEqual($data['map_points'][0], '42.886927111,-78.877383111');
        $this->assertEqual($data['map_points'][1], '34.425323111,-103.191544111');

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testWeeklySomePostsOneDay() {
        $this->instance->network = 'test_no_monthly';
        $builders = array();
        for ($i=0; $i<6; $i++) {
            $builders[] = $this->generatePost(true, 1);
            $builders[] = $this->generatePost(false, $i);
        }

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);
        $insight_plugin->generateInsight($this->instance, null, $posts, 3);
        $today = date('Y-m-d');
        $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
        $this->assertNotNull($result);
        $this->assertEqual($result->headline, "Tracking supermayor IRL.");
        $this->assertEqual($result->text, "Last week, supermayor attached a precise location on 1 day to a total of "
            . "6 posts. That's roughly 4 hours during which supermayor could be found in person.");

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testWeeklyTooFewPost() {
        $this->instance->network = 'test_no_monthly';
        $builders = array();
        for ($i=0; $i<6; $i++) {
            $builders[] = $this->generatePost(false, $i);
        }
        $builders[] = $this->generatePost(true, 1);
        $builders[] = $this->generatePost(true, 1);
        $builders[] = $this->generatePost(true, 1);

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);
        $insight_plugin->generateInsight($this->instance, null, $posts, 3);
        $today = date('Y-m-d');
        $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
        $this->assertNull($result);
    }

    public function testMonthlyNoPosts() {
        $builders = array();
        for ($i=0; $i<6; $i++) {
            $builders[] = $this->generatePost(false, $i);
        }

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);
        $insight_plugin->generateInsight($this->instance, null, $posts, 3);
        $today = date('Y-m-d');
        $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
        $this->assertNull($result);
    }

    public function testMonthlyOnePost() {
        $builders = array();
        for ($i=0; $i<6; $i++) {
            $builders[] = $this->generatePost(false, $i);
        }
        $builders[] = $this->generatePost(true, 1);

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);
        $insight_plugin->generateInsight($this->instance, null, $posts, 3);
        $today = date('Y-m-d');
        $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
        $this->assertNotNull($result);
        $this->assertEqual($result->headline, "Tracking @supermayor IRL.");
        $this->assertEqual($result->text, "Last month, @supermayor attached a precise location to 1 tweet. "
            ."That's roughly 45 minutes during which @supermayor could be found in person.");

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testMonthlySomePost() {
        $builders = array();
        for ($i=0; $i<6; $i++) {
            $builders[] = $this->generatePost(false, $i);
            $builders[] = $this->generatePost(true, $i*2);
        }

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);
        $insight_plugin->generateInsight($this->instance, null, $posts, 3);
        $today = date('Y-m-d');
        $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
        $this->assertNotNull($result);
        $this->assertEqual($result->headline, "Tracking @supermayor IRL.");
        $this->assertEqual($result->text, "Last month, @supermayor attached a precise location to 5 tweets. "
            ."That's roughly 3 hours during which @supermayor could be found in person.");

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testAlternateHeadlines() {
        $builders = array();
        for ($i=0; $i<6; $i++) {
            $builders[] = $this->generatePost(false, $i);
        }
        $builders[] = $this->generatePost(true, $i*2);

        $insight_plugin = new LocationAwarenessInsight();
        $post_dao = DAOFactory::getDAO('PostDAO');
        $posts = $post_dao->getAllPostsByUsernameOrderedBy($this->instance->network_username, $this->instance->network,
            $count=0, $order_by="pub_date", $in_last_x_days = 7,
            $iterator = false, $is_public = false);


        $headlines = array(
            null,
            "@supermayor's specific location was visible 1 time last month.",
            "Tracking @supermayor IRL.",
            "Every step @supermayor takes.",
            "Where in the world is @supermayor?",
        );
        for ($i=1; $i<=4; $i++) {
            TimeHelper::setTime($i);
            $insight_plugin->generateInsight($this->instance, null, $posts, 3);
            $today = date('Y-m-d');
            $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
            $this->assertNotNull($result);
            $this->assertEqual($result->headline, $headlines[$i]);
            $this->debug($this->getRenderedInsightInHTML($result));
            $this->debug($this->getRenderedInsightInEmail($result));
        }

        $builders[] = $this->generatePost(true, $i*2);
        $headlines[1] = "@supermayor's specific location was visible 2 times last month.";
        for ($i=1; $i<=4; $i++) {
            TimeHelper::setTime($i);
            $insight_plugin->generateInsight($this->instance, null, $posts, 3);
            $today = date('Y-m-d');
            $result = $this->insight_dao->getInsight($insight_plugin->slug, $this->instance->id, $today);
            $this->assertNotNull($result);
            $this->assertEqual($result->headline, $headlines[$i]);
            $this->debug($this->getRenderedInsightInHTML($result));
            $this->debug($this->getRenderedInsightInEmail($result));
        }
    }

    public function testIsPreciselyLocated() {
    }

    private function generatePost($is_geo, $days_ago) {
        return FixtureBuilder::build('posts', array(
            'geo' => $is_geo ? '1.12345678,2.12345678' : '-1.123456,-2.1234567',
            'network' => $this->instance->network,
            'author_username' => $this->instance->network_username,
            'post_text' => $is_geo ? 'Look where I am!' : 'I am hiding.',
            'pub_date' => (-1*$days_ago).'d'

        ));
    }
}

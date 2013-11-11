<?php
/**
 *
 * ThinkUp/tests/WebTestOfSessionAPILogin.php
 *
 * Copyright (c) 2013 Gina Trapani
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
 * Test of SessionAPILoginController
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2013 Gina Trapani
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 */

require_once dirname(__FILE__).'/init.tests.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/autorun.php';
require_once THINKUP_WEBAPP_PATH.'config.inc.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/web_tester.php';

class WebTestOfSessionAPILogin extends ThinkUpWebTestCase {

    public function setUp() {
        parent::setUp();
        $this->builders = self::buildData();
    }

    public function tearDown() {
        $this->builders = null;
        parent::tearDown();
    }

    protected function buildData() {
        $builders = array();

        $hashed_pass = ThinkUpTestLoginHelper::hashPasswordUsingDeprecatedMethod("secretpassword");

        $builders[] = FixtureBuilder::build('owners', array('id'=>1, 'email'=>'me@example.com', 'pwd'=>$hashed_pass,
        'pwd_salt'=>OwnerMySQLDAO::$default_salt, 'is_activated'=>1, 'is_admin'=>1, 'api_key_private'=>'aabbccdd'));

        $test_salt = 'test_salt';
        $password = ThinkUpTestLoginHelper::hashPasswordUsingCurrentMethod('secretpassword', $test_salt);

        $builders[] = FixtureBuilder::build('owners', array('id'=>2, 'email'=>'noprivapikey@example.com',
        'pwd'=>$password, 'pwd_salt'=>$test_salt, 'is_activated'=>1, 'is_admin'=>1, 'api_key_private'=>''));

        $builders[] = FixtureBuilder::build('owners', array('id'=>3, 'email'=>'notactivated@example.com',
        'pwd'=>$password, 'pwd_salt'=>$test_salt, 'is_activated'=>0, 'is_admin'=>0));

        $builders[] = FixtureBuilder::build('owners', array('id'=>4, 'email'=>'me2@example.com', 'pwd'=>$hashed_pass,
        'pwd_salt'=>OwnerMySQLDAO::$default_salt, 'is_activated'=>1, 'is_admin'=>0, 'api_key_private'=>'yayaya'));

        return $builders;
    }

    public function testRedirects() {
        //None set
        $this->get($this->url.'/api/v1/session/login.php');
        $this->assertText('No success redirect specified');

        //Redirects set but nothing else
        $this->get($this->url.'/api/v1/session/login.php?success_redir=success&failure_redir=failure');
        $this->assertHeader('testlocation', 'failure?msg=Email+must+not+be+empty.');
    }

    public function testCredentials() {
        //User email set but not API key
        $params = array('success_redir'=>'success', 'failure_redir'=>'failure', 'u'=>'nonexist@example.com');
        $param_str = '';
        foreach ($params as $key=>$value) {
            $param_str .= $key . "=" . urlencode($value)."&";
        }
        $this->get($this->url.'/api/v1/session/login.php?'.$param_str);
        //$this->showHeaders();
        $this->assertHeader('testlocation', 'failure?msg=API+key+must+not+be+empty.');

        //API key set but not user email
        $params = array('success_redir'=>'success', 'failure_redir'=>'failure', 'k'=>'apikeyyo');
        $param_str = '';
        foreach ($params as $key=>$value) {
            $param_str .= $key . "=" . urlencode($value)."&";
        }
        $this->get($this->url.'/api/v1/session/login.php?'.$param_str);
        //$this->showHeaders();
        $this->assertHeader('testlocation', 'failure?msg=Email+must+not+be+empty.');

        //User does not exist
        $params = array('success_redir'=>'success', 'failure_redir'=>'failure', 'u'=>'idontexist@example.com',
        'k'=>'apikeyyo');
        $param_str = '';
        foreach ($params as $key=>$value) {
            $param_str .= $key . "=" . urlencode($value)."&";
        }
        $this->get($this->url.'/api/v1/session/login.php?'.$param_str);
        //$this->showHeaders();
        $this->assertHeader('testlocation', 'failure?msg=Invalid+email.');


        //User is not activated
        $params = array('success_redir'=>'success', 'failure_redir'=>'failure', 'u'=>'notactivated@example.com',
        'k'=>'apikeyyo');
        $param_str = '';
        foreach ($params as $key=>$value) {
            $param_str .= $key . "=" . urlencode($value)."&";
        }
        $this->get($this->url.'/api/v1/session/login.php?'.$param_str);
        //$this->showHeaders();
        $this->assertHeader('testlocation', 'failure?msg=Inactive+account.');

        //User exists but doesn't have a private API key set
        $params = array('success_redir'=>'success', 'failure_redir'=>'failure', 'u'=>'noprivapikey@example.com',
        'k'=>'apikeyyo');
        $param_str = '';
        foreach ($params as $key=>$value) {
            $param_str .= $key . "=" . urlencode($value)."&";
        }
        $this->get($this->url.'/api/v1/session/login.php?'.$param_str);
        //$this->showHeaders();
        $this->assertHeader('testlocation', 'failure?msg=Invalid+API+key.');

        //User exists and has API key but it is incorrect
        $params = array('success_redir'=>'success', 'failure_redir'=>'failure', 'u'=>'me@example.com',
        'k'=>'aasdasdfafabbccdd');
        $param_str = '';
        foreach ($params as $key=>$value) {
            $param_str .= $key . "=" . urlencode($value)."&";
        }
        $this->get($this->url.'/api/v1/session/login.php?'.$param_str);
        //$this->showHeaders();
        $this->assertHeader('testlocation', 'failure?msg=Invalid+API+key.');

        //User 1 is logged in and user 2 attempts login
        $params = array('success_redir'=>'success', 'failure_redir'=>'failure', 'u'=>'me2@example.com',
        'k'=>'yayaya');
        $param_str = '';
        foreach ($params as $key=>$value) {
            $param_str .= $key . "=" . urlencode($value)."&";
        }
        $this->get($this->url.'/api/v1/session/login.php?'.$param_str);
        //$this->showHeaders();
        $this->assertHeader('testlocation', 'success?msg=Logged+in+successfully.');
    }
}

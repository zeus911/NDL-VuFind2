<?php
/**
 * Mink search actions test class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Mink;

/**
 * Mink search actions test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class SearchActionsTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        return static::failIfUsersExist();
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
    }

    /**
     * Search for the specified query.
     *
     * @param string $query Search term(s)
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function performSearch($query)
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '.searchForm [name="lookfor"]')->setValue($query);
        $this->findCss($page, '.btn.btn-primary')->click();
        $this->snooze();
        return $page;
    }

    /**
     * Test saving and clearing a search.
     *
     * @return void
     */
    public function testSaveSearch()
    {
        $page = $this->performSearch('test');
        $this->findCss($page, '.fa.fa-save')->click();
        $this->snooze();
        $this->findCss($page, '.createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm($page);
        $this->findCss($page, 'input.btn.btn-primary')->click();
        $this->snooze();
        $this->assertEquals(
            'Search saved successfully.',
            $this->findCss($page, '.alert.alert-success')->getText()
        );
    }

    /**
     * Test search history.
     *
     * @return void
     */
    public function testSearchHistory()
    {
        $page = $this->performSearch('foo');
        $page->findLink('Search History')->click();
        $this->snooze();
        // We should see our "foo" search in the history, but no saved
        // searches because we are logged out:
        $this->assertEquals('foo', $page->findLink('foo')->getText());
        $this->assertFalse(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertNull($page->findLink('test'));

        // Now log in and see if our saved search shows up (without making the
        // unsaved search go away):
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->assertEquals('foo', $page->findLink('foo')->getText());
        $this->assertTrue(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertEquals('test', $page->findLink('test')->getText());

        // Now purge unsaved searches, confirm that unsaved search is gone
        // but saved search is still present:
        $page->findLink('Purge unsaved searches')->click();
        $this->snooze();
        $this->assertNull($page->findLink('foo'));
        $this->assertEquals('test', $page->findLink('test')->getText());
    }

    /**
     * Test that user A cannot delete user B's favorites.
     *
     * @return void
     */
    public function testSavedSearchSecurity()
    {
        // Log in as user A and get the ID of their saved search:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/History');
        $page = $session->getPage();
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $delete = $page->findLink('Delete')->getAttribute('href');
        $page->findLink('Log Out')->click();
        $this->snooze();

        // Use user A's delete link, but try to execute it as user B:
        list($base, $params) = explode('?', $delete);
        $session->visit($this->getVuFindUrl() . '/MyResearch/SaveSearch?' . $params);
        $page = $session->getPage();
        $this->findCss($page, '.createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm(
            $page, ['username' => 'username2', 'email' => 'username2@example.com']
        );
        $this->findCss($page, 'input.btn.btn-primary')->click();
        $this->snooze();
        $page->findLink('Log Out')->click();
        $this->snooze();

        // Go back in as user A -- see if the saved search still exists.
        $page->findLink('Search History')->click();
        $this->snooze();
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->assertTrue(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertEquals('test', $page->findLink('test')->getText());
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers(['username1', 'username2']);
    }
}

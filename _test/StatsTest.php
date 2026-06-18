<?php

namespace dokuwiki\plugin\acknowledge\test;

use DokuWikiTest;

/**
 * Tests for the acknowledgement statistics aggregation
 * (helper_plugin_acknowledge::getStatistics).
 *
 * Verifies the per-namespace and wiki-wide completion counts, @group expansion,
 * and the current-vs-outdated semantics.
 *
 * @group plugin_acknowledge
 * @group plugins
 */
class StatsTest extends DokuWikiTest
{
    /** @var array */
    protected $pluginsEnabled = ['acknowledge', 'sqlite'];

    /** @var \helper_plugin_acknowledge */
    protected $helper;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        /** @var \auth_plugin_authplain $auth */
        global $auth;
        $auth->createUser('max', 'none', 'max', 'max@example.com', ['super']);
        $auth->createUser('regular', 'none', 'regular', 'regular@example.com', ['user']);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->helper = plugin_load('helper', 'acknowledge');

        $db = $this->helper->getDB();

        // pages across two top-level namespaces (lastmod 1000), incl. a sub-namespace page
        // that must roll up into its top-level namespace, plus a tracked page without assignees
        $db->query(
            "REPLACE INTO pages(page,lastmod) VALUES
                ('stats1:a', 1000),
                ('stats1:b', 1000),
                ('stats1:sub:d', 1000),
                ('stats2:c', 1000),
                ('stats1:noassign', 1000)"
        );

        // stats1:a -> required {regular, max(@super)}; regular current, max outdated
        $this->helper->setPageAssignees('stats1:a', 'regular, @super');
        $this->helper->importAcknowledgement('stats1:a', 'regular', 2000);
        $this->helper->importAcknowledgement('stats1:a', 'max', 500);

        // stats1:b -> required {regular}; current -> fully acknowledged
        $this->helper->setPageAssignees('stats1:b', 'regular');
        $this->helper->importAcknowledgement('stats1:b', 'regular', 2000);

        // stats1:sub:d -> required {regular}; current -> fully acknowledged; rolls up into 'stats1'
        $this->helper->setPageAssignees('stats1:sub:d', 'regular');
        $this->helper->importAcknowledgement('stats1:sub:d', 'regular', 2000);

        // stats2:c -> required {max(@super)}; never acknowledged
        $this->helper->setPageAssignees('stats2:c', '@super');
    }

    /**
     * Whole-wiki aggregation across both namespaces.
     */
    public function testTotals()
    {
        $stats = $this->helper->getStatistics();

        self::assertEquals(
            ['required' => 5, 'acked' => 3, 'pages' => 4],
            $stats['total']
        );
    }

    /**
     * Root drill-down: pages are grouped by their top-level namespace, plus @group expansion,
     * current-vs-outdated handling, and the haschildren flag for namespaces with deeper content.
     */
    public function testNamespaceBreakdown()
    {
        $stats = $this->helper->getStatistics();
        $ns = $stats['namespaces'];

        self::assertEquals(
            ['required' => 4, 'acked' => 3, 'pages' => 3, 'haschildren' => true],
            $ns['stats1']
        );

        self::assertEquals(
            ['required' => 1, 'acked' => 0, 'pages' => 1, 'haschildren' => false],
            $ns['stats2']
        );
    }

    /**
     * Pages without assignees are excluded from the statistics.
     */
    public function testPageWithoutAssigneesExcluded()
    {
        $stats = $this->helper->getStatistics();

        // only stats1 and stats2 namespaces, noassign contributes nothing
        self::assertEquals(['stats1', 'stats2'], array_keys($stats['namespaces']));
        self::assertSame(4, $stats['total']['pages']);
    }

    /**
     * Drilling into a namespace groups pages by their immediate child namespace, while the
     * total stays wiki-wide.
     */
    public function testDrilldown()
    {
        $stats = $this->helper->getStatistics('stats1');

        // total is always the whole wiki, regardless of the drill-down namespace
        self::assertEquals(
            ['required' => 5, 'acked' => 3, 'pages' => 4],
            $stats['total']
        );

        // within stats1: direct pages (a + b) group under 'stats1', the sub-namespace page
        // groups under 'stats1:sub'; stats2 is out of scope
        self::assertEquals(['stats1', 'stats1:sub'], array_keys($stats['namespaces']));

        // 'stats1' self group = direct pages a + b: required max+regular(a)+regular(b) = 3,
        // acked regular(a)+regular(b) = 2; no pages deeper than stats1:* here -> haschildren false
        self::assertEquals(
            ['required' => 3, 'acked' => 2, 'pages' => 2, 'haschildren' => false],
            $stats['namespaces']['stats1']
        );

        // 'stats1:sub' = page sub:d: required regular = 1, acked regular = 1
        self::assertEquals(
            ['required' => 1, 'acked' => 1, 'pages' => 1, 'haschildren' => false],
            $stats['namespaces']['stats1:sub']
        );
    }
}

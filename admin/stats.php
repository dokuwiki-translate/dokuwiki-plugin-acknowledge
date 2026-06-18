<?php

use dokuwiki\Extension\AdminPlugin;

/**
 * DokuWiki Plugin acknowledge (Admin Component)
 *
 * Acknowledgement statistics
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Anna Dabrowska <dokuwiki@cosmocode.de>
 */
class admin_plugin_acknowledge_stats extends AdminPlugin
{
    /** @inheritdoc */
    public function forAdminOnly()
    {
        return false;
    }

    /** @inheritDoc */
    public function getMenuText($language)
    {
        return $this->getLang('menu_stats');
    }

    /** @inheritdoc */
    public function handle()
    {
    }

    /** @inheritdoc */
    public function html()
    {
        global $INPUT;

        /** @var helper_plugin_acknowledge $helper */
        $helper = plugin_load('helper', 'acknowledge');

        $ns = trim($INPUT->str('ns'), ':');

        echo '<div class="plugin_acknowledgement_admin">';
        echo '<h1>' . $this->getLang('menu_stats') . '</h1>';

        $stats = $helper->getStatistics($ns);

        // whole-wiki summary
        $this->htmlSummary($stats['total']);

        // back-link to root
        if ($ns !== '') {
            echo '<p class="stats-back">' . $this->nsLink('', $this->getLang('statsTotalLink')) . '</p>';
            echo '<h2>' . $this->getLang('statsNamespace') . ' ' . $ns . '</h2>';
        }

        if (empty($stats['namespaces'])) {
            echo '<p>' . $this->getLang('nothingfound') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table>';
        echo '<tr>';
        echo '<th>' . $this->getLang('statsSubnamespace') . '</th>';
        echo '<th>' . $this->getLang('statsAcked') . '</th>';
        echo '<th>' . $this->getLang('statsRequired') . '</th>';
        echo '<th>' . $this->getLang('statsPages') . '</th>';
        echo '<th>' . $this->getLang('statsRatio') . '</th>';
        echo '</tr>';

        foreach ($stats['namespaces'] as $key => $data) {
            $this->htmlRow($this->nsLabel($key, $ns, $data['haschildren']), $data);
        }

        echo '</table>';
        echo '</div>';
    }

    /**
     * Whole-wiki summary
     *
     * @param array $total {required:int, acked:int, pages:int}
     * @return void
     */
    protected function htmlSummary(array $total): void
    {
        $ratio = $total['required'] ? round($total['acked'] * 100 / $total['required']) : 0;

        echo '<div class="stats-summary">';
        echo '<h2>' .  $this->getLang('statsTotal') . '</h2>';
        echo '<ul>';
        echo '<li><strong>' . $this->getLang('statsAcked') . ':</strong> ' . $total['acked'] . '</li>';
        echo '<li><strong>' . $this->getLang('statsRequired') . ':</strong> ' . $total['required'] . '</li>';
        echo '<li><strong>' . $this->getLang('statsPages') . ':</strong> ' . $total['pages'] . '</li>';
        echo '<li><strong>' . $this->getLang('statsRatio') . ':</strong> ' . $ratio . '%' . '</li>';
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Namespace label, linked if it has children
     *
     * @param string $key Sub-namespace key (relative to the current namespace)
     * @param string $ns Currently explored namespace
     * @param bool $haschildren
     * @return string HTML
     */
    protected function nsLabel(string $key, string $ns, bool $haschildren): string
    {
        if ($key === '') {
            return $this->getLang('statsRoot');
        }
        if ($key === $ns) {
            return $this->getLang('statsHere');
        }

        // show the part relative to the current namespace
        $relative = $ns === '' ? $key : substr($key, strlen($ns) + 1);
        $label = $relative . ':';

        return $haschildren ? $this->nsLink($key, $label) : $label;
    }

    /**
     * Link to a namespace drill-down view
     *
     * @param string $ns
     * @param string $label
     * @return string
     */
    protected function nsLink(string $ns, string $label): string
    {
        global $ID;

        $params = ['do' => 'admin', 'page' => 'acknowledge_stats'];
        if ($ns !== '') $params['ns'] = $ns;

        return '<a href="' . wl($ID, $params) . '">' . $label . '</a>';
    }

    /**
     * Render a single statistics row
     *
     * @param string $label
     * @param array $data {required:int, acked:int, pages:int}
     * @return void
     */
    protected function htmlRow(string $label, array $data): void
    {
        $ratio = $data['required'] ? round($data['acked'] * 100 / $data['required']) : 0;

        echo '<tr>';
        echo '<td>' . $label . '</td>';
        echo '<td class="stats-num">' . $data['acked'] . '</td>';
        echo '<td class="stats-num">' . $data['required'] . '</td>';
        echo '<td class="stats-num">' . $data['pages'] . '</td>';
        echo '<td class="stats-num">' . $ratio . '%</td>';
        echo '</tr>';
    }

    /** @inheritDoc */
    public function getTOC()
    {
        return (new admin_plugin_acknowledge_report())->getTOC();
    }
}

<?php namespace Aero\Services\Updates;

use Db;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        // requires / includes se fusionan en built_with.
        foreach (Db::table('aero_services_services')->whereNotNull('plugin_links')->get(['id', 'plugin_links']) as $row) {
            $links = json_decode($row->plugin_links, true);
            if (!is_array($links)) {
                continue;
            }
            foreach ($links as &$l) {
                if (in_array($l['relation'] ?? null, ['requires', 'includes'], true)) {
                    $l['relation'] = 'built_with';
                }
            }
            Db::table('aero_services_services')->where('id', $row->id)->update(['plugin_links' => json_encode($links)]);
        }
    }

    public function down()
    {
    }
};

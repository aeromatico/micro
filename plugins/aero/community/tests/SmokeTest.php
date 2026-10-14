<?php namespace Aero\Community\Tests;

use Aero\Community\Classes\Reporter;
use Aero\Community\Models\Post;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_community_posts', 'aero_community_comments', 'aero_community_votes'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Community\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testIdeasAndBugsGetAResponseCommitment(): void
    {
        $idea = Post::create(['tenant_id' => 1, 'user_id' => 1, 'type' => 'idea', 'title' => 'x', 'body' => 'cuerpo']);
        $chat = Post::create(['tenant_id' => 1, 'user_id' => 1, 'type' => 'discussion', 'title' => 'y', 'body' => 'cuerpo']);

        $this->assertNotNull($idea->sla_due_at);
        $this->assertNull($chat->sla_due_at);
        $this->assertCount(1, Post::awaitingResponse()->get());
    }

    public function testPrivateReportsAreOnlyVisibleToTheirTenant(): void
    {
        Post::create(['tenant_id' => 1, 'user_id' => 1, 'type' => 'bug', 'visibility' => 'private', 'title' => 'a', 'body' => 'secreto A']);
        Post::create(['tenant_id' => 2, 'user_id' => 2, 'type' => 'bug', 'visibility' => 'private', 'title' => 'b', 'body' => 'secreto B']);
        Post::create(['tenant_id' => 2, 'user_id' => 2, 'type' => 'idea', 'visibility' => 'public', 'title' => 'c', 'body' => 'pública']);

        // Sin usuario/tenant resoluble (falla cerrado): solo lo público.
        $visible = Post::visibleToCurrent()->pluck('title')->all();
        $this->assertSame(['c'], $visible);
    }

    public function testScreenshotOnlyAcceptsRealPng(): void
    {
        $this->assertNull(Reporter::storeScreenshot('data:image/png;base64,' . base64_encode('<?php echo 1;')));
        $this->assertNull(Reporter::storeScreenshot('data:image/svg+xml;base64,AAAA'));
    }
}

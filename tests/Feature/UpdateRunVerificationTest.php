<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Actions\ProcessInventoryResult;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\UpdateRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateRunVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_whose_version_never_moved_flips_to_failed_with_message(): void
    {
        [$site, $command] = $this->siteWithSafeRun(from: '1.12.2');
        $this->scanSite($site, ['novamira' => ['1.12.2', true]]);

        $run = UpdateRun::query()->sole();
        $this->assertSame(UpdateRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->verified_at);
        $this->assertStringContainsString('still on 1.12.2', $run->message);
        $this->assertFalse($command->fresh()->result['verification']['ok']);
        $this->assertStringContainsString('did not take', $command->fresh()->result['verification']['message']);
    }

    public function test_run_where_the_version_moved_stays_updated(): void
    {
        [$site] = $this->siteWithSafeRun(from: '1.12.2');
        $this->scanSite($site, ['novamira' => ['1.12.7', true]]);

        $run = UpdateRun::query()->sole();
        $this->assertSame(UpdateRun::STATUS_UPDATED, $run->status);
        $this->assertNotNull($run->verified_at);
        $this->assertNull($run->message);
    }

    public function test_run_where_the_update_offer_cleared_counts_as_taken(): void
    {
        [$site] = $this->siteWithSafeRun(from: '1.12.2');
        $this->scanSite($site, ['novamira' => ['1.12.7', false]]);

        $run = UpdateRun::query()->sole();
        $this->assertSame(UpdateRun::STATUS_UPDATED, $run->status);
        $this->assertNotNull($run->verified_at);
    }

    public function test_runs_outside_the_window_are_not_judged(): void
    {
        [$site] = $this->siteWithSafeRun(from: '1.12.2');
        UpdateRun::query()->update(['created_at' => now()->subHours(3)]);

        $this->scanSite($site, ['novamira' => ['1.12.2', true]]);

        $run = UpdateRun::query()->sole();
        $this->assertSame(UpdateRun::STATUS_UPDATED, $run->status);
        $this->assertNull($run->verified_at);
    }

    public function test_plain_run_with_a_still_offered_update_is_annotated(): void
    {
        $site = $this->site();
        SiteCommand::query()->create([
            'site_id' => $site->id,
            'type' => 'update.run',
            'payload' => ['context' => 'plugin', 'slug' => 'wordpress'],
            'status' => SiteCommand::STATUS_COMPLETED,
            'result' => ['data' => ['update' => ['ok' => true, 'message' => 'Updated']]],
            'expires_at' => now()->addHour(),
        ]);

        $this->scanSite($site, ['wordpress' => ['7.1', true]]);

        $command = SiteCommand::query()->sole();
        $this->assertTrue($command->payload['verified']);
        $this->assertFalse($command->result['verification']['ok']);
        $this->assertStringContainsString('still offered', $command->result['verification']['message']);

        // A second scan must not re-annotate the same command.
        $this->scanSite($site, ['wordpress' => ['7.1', true]]);
        $this->assertTrue($command->fresh()->payload['verified']);
    }

    public function test_plain_run_that_cleared_is_marked_verified_only(): void
    {
        $site = $this->site();
        SiteCommand::query()->create([
            'site_id' => $site->id,
            'type' => 'update.run',
            'payload' => ['context' => 'plugin', 'slug' => 'wordpress'],
            'status' => SiteCommand::STATUS_COMPLETED,
            'result' => ['data' => ['update' => ['ok' => true]]],
            'expires_at' => now()->addHour(),
        ]);

        $this->scanSite($site, ['wordpress' => ['7.1.2', false]]);

        $command = SiteCommand::query()->sole();
        $this->assertTrue($command->payload['verified']);
        $this->assertArrayNotHasKey('verification', $command->result);
    }

    /**
     * A site with one safe-update run (reported success) and its command.
     *
     * @return array{0: Site, 1: SiteCommand}
     */
    private function siteWithSafeRun(string $from): array
    {
        $site = $this->site();
        $command = SiteCommand::query()->create([
            'site_id' => $site->id,
            'type' => 'update.safe',
            'payload' => ['context' => 'plugin', 'slug' => 'novamira'],
            'status' => SiteCommand::STATUS_COMPLETED,
            'result' => ['data' => ['safe' => ['ok' => true]]],
            'expires_at' => now()->addHour(),
        ]);

        UpdateRun::query()->create([
            'site_id' => $site->id,
            'context' => 'plugin',
            'slug' => 'novamira',
            'command_id' => $command->id,
            'from_version' => $from,
            'to_version' => '1.12.7',
            'status' => UpdateRun::STATUS_UPDATED,
            'files_backup' => true,
        ]);

        return [$site, $command];
    }

    /**
     * Push a connector-style inventory snapshot through the pipeline;
     * versions is a map of slug => [version, update_available].
     *
     * @param  array<string, array{0: string, 1: bool}>  $plugins
     */
    private function scanSite(Site $site, array $plugins): void
    {
        $items = [];

        foreach ($plugins as $slug => [$version, $updateAvailable]) {
            $items[] = [
                'slug' => $slug,
                'name' => ucfirst($slug),
                'version' => $version,
                'update_available' => $updateAvailable,
                'update_version' => $updateAvailable ? '9.9.9' : null,
                'active' => true,
            ];
        }

        app(ProcessInventoryResult::class)($site, [
            'core' => null,
            'plugins' => $items,
            'themes' => [],
        ]);
    }

    private function site(): Site
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);

        return Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
        ]);
    }
}

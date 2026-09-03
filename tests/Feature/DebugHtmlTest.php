<?php
namespace Tests\Feature;
use App\Actions\CreateWorkspaceForUser;
use App\Filament\Pages\ConnectSite;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DebugHtmlTest extends TestCase
{
    use RefreshDatabase;
    public function test_debug(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $this->actingAs($owner);
        Filament::setTenant($workspace);
        $c = Livewire::test(ConnectSite::class)
            ->fillForm(['name' => 'BT Demo', 'url' => 'https://demo.betatech.co/', 'project_id' => $project->id])
            ->call('connect');
        file_put_contents('/tmp/connect_rendered.html', $c->html());
        echo "pairingCode prop: " . ($c->instance()->pairingCode ? substr($c->instance()->pairingCode, 0, 9) . '...' : 'NULL');
        echo "\nschema has section: " . (str_contains($c->html(), 'Finish pairing') ? 'YES' : 'NO');
    }
}

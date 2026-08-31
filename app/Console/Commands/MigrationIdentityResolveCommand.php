<?php

namespace App\Console\Commands;

use App\Migration\Phases\MigrationRunManager;
use App\Migration\Phases\Support\CustomerIdentityResolver;
use Illuminate\Support\Facades\File;

class MigrationIdentityResolveCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:identity-resolve
        {--apply : Write approved identity resolutions to migration_entity_maps}
        {--run= : Migration run UUID}';

    protected $description = 'Apply approved duplicate-NRC identity resolutions (target maps only; legacy read-only)';

    public function handle(CustomerIdentityResolver $resolver, MigrationRunManager $runManager): int
    {
        if (! $this->option('apply')) {
            $this->warn('Dry-run — use --apply to write map annotations.');
        }

        $runId = null;
        if ($this->option('apply')) {
            $run = $runManager->start('identity-resolve', 'approved', $this->runUuid());
            $runId = $run['id'];
        }

        $result = $this->option('apply')
            ? $resolver->applyApprovedResolutions($runId)
            : [
                'would_apply' => true,
                'duplicate_groups_resolved' => $resolver->duplicateGroupsResolved(),
                'pending_duplicate_groups' => \App\Migration\Phases\Support\IdentityResolutionCatalog::duplicateNrcKeys()->count()
                    - count(\App\Migration\Phases\Support\IdentityResolutionCatalog::approved()),
            ];

        $path = base_path('docs/data-migration/tools/customer-identity-resolutions-applied.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Identity resolution '.($this->option('apply') ? 'applied' : 'preview'));
        if (! ($result['duplicate_groups_resolved'] ?? false)) {
            $this->warn('Unresolved duplicate NRC groups remain — resolve them on the migration dashboard Identity tab.');
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT));
        $this->line("Output: {$path}");

        return self::SUCCESS;
    }
}

<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoSignal;

/**
 * Archiviert Legacy-Signale (status=resolved) — alles OHNE signal_definition_id,
 * also rec_%, hardcoded detect-signals und budget_pressure. Die definition-
 * getriebenen Signale (gesteuertes System) bleiben unberührt.
 *
 * Dry-Run per Default; erst --confirm führt aus.
 */
class ArchiveLegacySignals extends Command
{
    protected $signature = 'seo:archive-legacy-signals
                            {--team= : Nur dieses Team}
                            {--confirm : Tatsächlich archivieren (sonst nur Dry-Run)}';

    protected $description = 'Archiviert Legacy-Signale (ohne Definition) auf status=resolved. Dry-Run per Default.';

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        $teamId = $this->option('team');

        $base = SeoSignal::whereNull('signal_definition_id')
            ->where('status', '!=', 'resolved')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId));

        $total = (clone $base)->count();
        if ($total === 0) {
            $this->info('Keine Legacy-Signale zum Archivieren.');

            return self::SUCCESS;
        }

        $byType = (clone $base)
            ->selectRaw('signal_type, count(*) as c')
            ->groupBy('signal_type')
            ->orderByDesc('c')
            ->get();

        $this->info(($confirm ? 'Archiviere' : 'Dry-Run — es würden archiviert').": {$total} Legacy-Signale");
        $this->table(
            ['Signal-Typ', 'Anzahl'],
            $byType->map(fn ($r) => [$r->signal_type, $r->c])->all()
        );

        if (! $confirm) {
            $this->warn('Dry-Run. Nichts verändert. Mit --confirm tatsächlich archivieren.');

            return self::SUCCESS;
        }

        $updated = (clone $base)->update(['status' => 'resolved']);
        $this->info("Fertig. {$updated} Legacy-Signale archiviert (status=resolved).");

        return self::SUCCESS;
    }
}

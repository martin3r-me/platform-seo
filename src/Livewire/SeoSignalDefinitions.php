<?php

namespace Platform\Seo\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoSignalDefinition;
use Platform\Seo\Models\SeoUrlList;

/**
 * Verwaltung der Signal-Definitionen — DB-Objekte, in der UI bearbeitbar.
 * Der andere Weg rein ist per LLM-Tool (seo.signal_definitions.*). docs/SIGNALS-CONCEPT.md.
 */
class SeoSignalDefinitions extends Component
{
    use ResolvesTeamSettings;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $description = '';
    public string $patternType = 'striking_distance';
    public string $severity = 'warning';
    public string $frequency = 'daily';
    public string $scopeType = 'all';
    public string $scopeValue = '';
    public string $conditionsJson = '';
    public bool $enrichWithAi = false;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'description', 'scopeValue', 'enrichWithAi']);
        $this->patternType = 'striking_distance';
        $this->severity = 'warning';
        $this->frequency = 'daily';
        $this->scopeType = 'all';
        $this->loadDefaultConditions();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $def = SeoSignalDefinition::where('team_id', (int) $this->seoSettings->team_id)->findOrFail($id);
        $this->editingId = $def->id;
        $this->name = $def->name;
        $this->description = $def->description ?? '';
        $this->patternType = $def->pattern_type;
        $this->severity = $def->severity;
        $this->frequency = $def->frequency;
        $this->scopeType = $def->scope_type;
        $this->scopeValue = (string) ($def->scope_value['entity_id'] ?? $def->scope_value['list_id'] ?? '');
        $this->conditionsJson = json_encode($def->conditions ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->enrichWithAi = (bool) $def->enrich_with_ai;
        $this->showModal = true;
    }

    /** Beim Musterwechsel im Create die Default-Conditions vorbelegen. */
    public function updatedPatternType(): void
    {
        if (! $this->editingId) {
            $this->loadDefaultConditions();
        }
    }

    protected function loadDefaultConditions(): void
    {
        $catalog = SeoSignalDefinition::patternCatalog();
        $conditions = $catalog[$this->patternType]['conditions'] ?? [];
        $this->conditionsJson = json_encode($conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'patternType' => ['required', Rule::in(SeoSignalDefinition::patternTypes())],
            'severity' => ['required', Rule::in(SeoSignalDefinition::SEVERITIES)],
            'frequency' => ['required', Rule::in(SeoSignalDefinition::FREQUENCIES)],
            'scopeType' => ['required', Rule::in(SeoSignalDefinition::SCOPE_TYPES)],
        ]);

        $conditions = json_decode($this->conditionsJson ?: '{}', true);
        if (! is_array($conditions)) {
            $this->addError('conditionsJson', 'Ungültiges JSON.');

            return;
        }

        $scopeVal = null;
        if (in_array($this->scopeType, ['entity', 'entity_subtree'], true) && $this->scopeValue !== '') {
            $scopeVal = ['entity_id' => (int) $this->scopeValue];
        } elseif ($this->scopeType === 'list' && $this->scopeValue !== '') {
            $scopeVal = ['list_id' => (int) $this->scopeValue];
        }

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'pattern_type' => $this->patternType,
            'conditions' => $conditions,
            'scope_type' => $this->scopeType,
            'scope_value' => $scopeVal,
            'frequency' => $this->frequency,
            'severity' => $this->severity,
            'enrich_with_ai' => $this->enrichWithAi,
        ];

        if ($this->editingId) {
            SeoSignalDefinition::where('team_id', (int) $this->seoSettings->team_id)
                ->findOrFail($this->editingId)
                ->update($data);
        } else {
            SeoSignalDefinition::create($data + [
                'team_id' => (int) $this->seoSettings->team_id,
                'created_by' => auth()->id(),
                'is_active' => true,
            ]);
        }

        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        $def = SeoSignalDefinition::where('team_id', (int) $this->seoSettings->team_id)->findOrFail($id);
        $def->is_active = ! $def->is_active;
        $def->save();
    }

    public function deleteDefinition(int $id): void
    {
        SeoSignalDefinition::where('team_id', (int) $this->seoSettings->team_id)->findOrFail($id)->delete();
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;

        $definitions = SeoSignalDefinition::where('team_id', $teamId)
            ->withCount('signals')
            ->orderBy('name')
            ->get();

        // Für die Scope-Auswahl per Select: Team-Listen + Org-Entities.
        $lists = SeoUrlList::whereHas('urls', fn ($q) => $q->where('seo_urls.team_id', $teamId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $entities = collect();
        try {
            $cls = \Platform\Organization\Models\OrganizationEntity::class;
            if (class_exists($cls)) {
                $entities = $cls::where('team_id', $teamId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }
        } catch (\Throwable $e) {
            // Organization nicht geladen — Fallback auf ID-Eingabe im Blade
        }

        return view('seo::livewire.seo-signal-definitions', [
            'definitions' => $definitions,
            'catalog' => SeoSignalDefinition::patternCatalog(),
            'lists' => $lists,
            'entities' => $entities,
        ])->layout('platform::layouts.app');
    }
}

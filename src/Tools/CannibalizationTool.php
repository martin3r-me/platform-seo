<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoUrlService;

class CannibalizationTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.cannibalization.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/cannibalization - Erkennt Keyword-Kannibalisierung: Keywords, für die mehrere eigene URLs ranken und sich gegenseitig Sichtbarkeit wegnehmen. Optional: domain (auf eine Domain einschränken), limit (Standard 50, max 200), offset. Ergebnis nach Suchvolumen absteigend.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'domain' => [
                    'type' => 'string',
                    'description' => 'Nur Kannibalisierung innerhalb dieser Domain (z.B. "tm-foodsolutions.de", inkl. Subdomains).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Anzahl Keywords (Standard 50, max 200). Verhindert überlange Antworten.',
                ],
                'offset' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $service = app(SeoUrlService::class);
            $domain = $this->normalizeDomain($arguments['domain'] ?? null);
            $data = $service->getCannibalization($team->id, $domain);

            $total = is_array($data) ? count($data) : 0;
            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $offset = max((int) ($arguments['offset'] ?? 0), 0);
            $slice = array_slice($data, $offset, $limit);

            return ToolResult::success([
                'cannibalization' => $slice,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'domain' => $domain,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Normalisiert eine Domain-Eingabe: entfernt Schema, Pfad und führendes "www.".
     */
    private function normalizeDomain(mixed $input): ?string
    {
        if (!is_string($input) || trim($input) === '') {
            return null;
        }
        $host = strtolower(trim($input));
        $host = preg_replace('#^[a-z]+://#', '', $host);
        $host = explode('/', $host, 2)[0];
        $host = preg_replace('#^www\.#', '', $host);
        $host = trim($host);

        return $host !== '' ? $host : null;
    }
}

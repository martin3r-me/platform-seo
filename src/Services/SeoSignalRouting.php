<?php

namespace Platform\Seo\Services;

/**
 * Zentrale Routing-Klassifikation (docs/SIGNALS-CONCEPT.md §4 Arbeitskette).
 *
 * Ordnet jedem Signal-Muster eine Änderungsart zu und daraus ein Arbeitsziel:
 *  - content     → Content-Brief (SEO-intern, inhaltliche Arbeit)
 *  - page_edit   → Flynk-Aufgabe (Änderung an bestehender Seite)
 *  - structural  → Flynk-Aufgabe (Konsolidierung / Redirect)
 *
 * Bewusst EINE Stelle: ändert sich die Zuordnung, folgen alle Signale sofort.
 * Ein Per-Definition-Override kann später hier andocken (ohne Streuung).
 */
class SeoSignalRouting
{
    public const KIND_CONTENT = 'content';
    public const KIND_PAGE_EDIT = 'page_edit';
    public const KIND_STRUCTURAL = 'structural';
    public const KIND_TECHNICAL = 'technical';
    public const KIND_OFFPAGE = 'offpage';

    /** Muster → Änderungsart. */
    private const PATTERN_KIND = [
        'thin_content' => self::KIND_CONTENT,
        'content_gap' => self::KIND_CONTENT,
        'cluster_underperformance' => self::KIND_CONTENT,
        'striking_distance' => self::KIND_PAGE_EDIT,
        'position_drop' => self::KIND_PAGE_EDIT,
        'position_gain' => self::KIND_PAGE_EDIT,
        'lost_ranking' => self::KIND_PAGE_EDIT,
        'decay' => self::KIND_PAGE_EDIT,
        'cannibalization' => self::KIND_STRUCTURAL,
        'page_retire' => self::KIND_STRUCTURAL,
        'page_broken' => self::KIND_TECHNICAL,
        'backlink_gap' => self::KIND_OFFPAGE,
    ];

    public const TARGET_CONTENT_BRIEF = 'content_brief';
    public const TARGET_FLYNK_TASK = 'flynk_task';

    public static function kindFor(string $pattern): string
    {
        return self::PATTERN_KIND[$pattern] ?? self::KIND_PAGE_EDIT;
    }

    /** Wohin die Arbeit fließt: content → Content-Brief (intern), sonst → Flynk-Aufgabe. */
    public static function targetFor(string $kind): string
    {
        return $kind === self::KIND_CONTENT
            ? self::TARGET_CONTENT_BRIEF
            : self::TARGET_FLYNK_TASK;
    }

    public static function targetForPattern(string $pattern): string
    {
        return self::targetFor(self::kindFor($pattern));
    }

    /** Menschlich lesbares Label je Änderungsart (für UI/Push). */
    public static function label(string $kind): string
    {
        return [
            self::KIND_CONTENT => 'Inhalt',
            self::KIND_PAGE_EDIT => 'Seitenänderung',
            self::KIND_STRUCTURAL => 'Struktur/Redirect',
            self::KIND_TECHNICAL => 'Technik',
            self::KIND_OFFPAGE => 'Backlinks',
        ][$kind] ?? $kind;
    }
}

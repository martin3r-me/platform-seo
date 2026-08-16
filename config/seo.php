<?php

return [
    'routing' => [
        'mode' => env('SEO_MODE', 'path'),
        'prefix' => 'seo',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'seo.dashboard',
        'icon'  => 'heroicon-o-magnifying-glass-circle',
        'order' => 45,
    ],

    'sidebar' => [
        [
            'group' => 'SEO',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'seo.dashboard',
                    'icon'  => 'heroicon-o-chart-bar-square',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Herkunft (Provenance) — woher stammt eine URL?
    |--------------------------------------------------------------------------
    | Owner-Achse des Werkzeugs. Abgeleitet aus seo_url_registrations.source_module.
    | 'seo' = Agentur/manuell (Default), sonst das Quell-Modul (syltjunkie …).
    | is_own=false wird als 'competitor' geführt. Label + Badge-Klassen zentral.
    */
    'provenance' => [
        'seo'        => ['label' => 'Agentur',      'classes' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'syltjunkie' => ['label' => 'Syltjunkie',   'classes' => 'bg-violet-50 text-violet-700 border-violet-200'],
        'competitor' => ['label' => 'Wettbewerber', 'classes' => 'bg-amber-50 text-amber-700 border-amber-200'],
    ],

    /*
    |--------------------------------------------------------------------------
    | In-Modul-Hilfe (Konzept-Anker + Pro-Linse-Banner)
    |--------------------------------------------------------------------------
    | Eine Quelle für alle Erklärungstexte. Das „?" in der Sidebar öffnet den
    | Konzept-Anker; jede Linse zeigt oben ein wegklickbares Banner aus 'lenses'.
    */
    'help' => [
        'concept' => [
            'title' => 'So funktioniert SEO',
            'intro' => 'Alles hier ist eine Linse auf dieselbe Grundlage: deine URLs und ihre zentral gemessenen Signale. Der Weg von der Idee zum Ergebnis läuft in Etappen.',
            'pipeline' => [
                ['step' => 'Modellieren', 'text' => 'Struktur schaffen — welche Entitäten/Knoten gehören zusammen (Kunde, Marke, Thema).'],
                ['step' => 'Nachfrage',   'text' => 'Keywords — wonach wird gesucht, mit welchem Volumen und welcher Absicht.'],
                ['step' => 'Cluster',     'text' => 'Keywords zu strategischen Themen bündeln und deren Erfolg über die Zeit messen.'],
                ['step' => 'Erden',       'text' => 'Signale — Sichtbarkeit, Rankings, Traffic, Backlinks: die reale Messung je URL.'],
                ['step' => 'Content',     'text' => 'Aus Lücken und Signalen werden konkrete Empfehlungen (Maßnahmen).'],
                ['step' => 'Distribution','text' => 'Empfehlungen laufen in den Kontext (Org-Baum), zurück ins Quell-Modul und nach Flynk zum Kunden.'],
            ],
            'lenses_intro' => 'Die Navigation sind Blickwinkel auf genau diese Grundlage:',
            'lenses' => [
                'URLs — die gemessenen Seiten (die Atome).',
                'Listen — manuelle Gruppen von URLs.',
                'Cluster — Themen-/Keyword-Gruppen als strategische Einheit.',
                'Wettbewerber — die fremden URLs (is_own = false) auf denselben Keywords.',
                'Empfehlungen — die abgeleiteten Handlungen aus den Signalen.',
                'Kontext — dieselben URLs, gruppiert am Org-Baum (Kunde/Knoten).',
            ],
        ],

        'lenses' => [
            'dashboard' => [
                'title' => 'Dashboard',
                'what'  => 'Der Gesamtüberblick: wie sichtbar bist du, wo bewegt sich etwas, was ist dringend. Startpunkt, kein Arbeitsplatz.',
                'next'  => ['label' => 'Signale ansehen', 'route' => 'seo.signals'],
            ],
            'recommendations' => [
                'title' => 'Empfehlungen',
                'what'  => 'Konkrete Maßnahmen, die das Modul aus den Signalen ableitet — nach Wirkung priorisiert. Das ist die Handlungsliste.',
                'next'  => ['label' => 'Betroffene URLs ansehen', 'route' => 'seo.urls'],
            ],
            'clusters' => [
                'title' => 'Cluster',
                'what'  => 'Die strategische Einheit: Keywords zu Themen gebündelt. Abdeckung, Sichtbarkeit und Trajektorie zeigen, ob ein Thema gewonnen wird.',
            ],
            'lists' => [
                'title' => 'Listen',
                'what'  => 'Manuelle Gruppen von URLs — nützlich für Projekte oder Auswertungen. Für die strategische Sicht nutze eher Cluster oder Kontext.',
            ],
            'urls' => [
                'title' => 'URLs',
                'what'  => 'Die Atome des Moduls: jede registrierte Seite wird zentral gemessen (Keywords, Rankings, Backlinks, On-Page). „Nach Kontext" gruppiert sie am Org-Baum.',
            ],
            'competitors' => [
                'title' => 'Wettbewerber',
                'what'  => 'Fremde Domains (is_own = false), die auf denselben Keywords ranken wie du — team-weit statt pro Liste. Die reale Konkurrenz um deine Themen.',
            ],
            'context' => [
                'title' => 'Kontext',
                'what'  => 'Die SEO-Sicht eines Org-Knotens (z. B. Kunde): alle hier verankerten URLs samt Signalen. Hier laufen die Daten in den Baum — und weiter nach Flynk.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry Presets
    |--------------------------------------------------------------------------
    */
    'industry_presets' => [
        'health' => [
            'label' => 'Gesundheitswesen',
            'seed_keywords' => ['praxissoftware', 'patientenverwaltung', 'telemedizin'],
            'topics' => ['praxis', 'patient', 'arzt', 'klinik', 'gesundheit'],
            'intents' => [
                'transactional' => ['kaufen', 'preis', 'kosten', 'vergleich', 'test'],
                'informational' => ['was ist', 'wie', 'erfahrung', 'ratgeber'],
                'navigational' => ['login', 'anmelden', 'app'],
            ],
        ],
        'saas' => [
            'label' => 'SaaS / Software',
            'seed_keywords' => ['software', 'tool', 'plattform'],
            'topics' => ['software', 'tool', 'api', 'integration', 'automatisierung'],
            'intents' => [
                'transactional' => ['kaufen', 'preis', 'demo', 'free trial', 'alternative'],
                'informational' => ['was ist', 'vergleich', 'erfahrung', 'tutorial'],
                'navigational' => ['login', 'anmelden', 'dashboard'],
            ],
        ],
        'ecommerce' => [
            'label' => 'E-Commerce',
            'seed_keywords' => ['online shop', 'bestellen', 'lieferung'],
            'topics' => ['produkt', 'shop', 'bestellung', 'lieferung', 'retoure'],
            'intents' => [
                'transactional' => ['kaufen', 'bestellen', 'preis', 'günstig', 'angebot'],
                'informational' => ['test', 'erfahrung', 'bewertung', 'vergleich'],
                'navigational' => ['login', 'konto', 'tracking'],
            ],
        ],
        'gastro' => [
            'label' => 'Gastronomie / Catering',
            'seed_keywords' => ['catering', 'eventcatering', 'fingerfood'],
            'topics' => ['catering', 'event', 'buffet', 'menü', 'hochzeit'],
            'intents' => [
                'transactional' => ['bestellen', 'buchen', 'preis', 'anfrage'],
                'informational' => ['ideen', 'tipps', 'rezept', 'planung'],
                'navigational' => ['speisekarte', 'kontakt', 'standort'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signal Detection Thresholds
    |--------------------------------------------------------------------------
    */
    'signals' => [
        'volume_spike_threshold' => 1.0,      // 100% increase
        'volume_drop_threshold' => -0.5,       // 50% decrease
        'position_rise_threshold' => 5,        // 5+ positions gained
        'position_drop_threshold' => -5,       // 5+ positions lost
        'opportunity_min_volume' => 200,
        'onpage_fresh_days' => 21,             // KI erdet sich nur an Crawl-Daten, die höchstens so alt sind

        // Governance: Signale sind ein gesteuerter Arbeitsvorrat, keine Flut.
        'wip_limit' => 5,                      // max. gleichzeitig offene definition-getriebene Signale
        'daily_new_limit' => 3,                // max. neue Signale pro Tag (Nachschub erst wenn Platz frei)
    ],

    /*
    |--------------------------------------------------------------------------
    | Setup / Aufbau-Reife — Vorgabe für einen belastbaren Kunden-Start
    |--------------------------------------------------------------------------
    | Für Kunden bei Null: wie viele Wettbewerber braucht es, damit das
    | Keyword-Umfeld aussagekräftig ist? Die Werkbank fordert das aktiv ein.
    */
    'setup' => [
        'competitors_min' => 3,    // Minimum für eine brauchbare Marktkarte
        'competitors_ideal' => 5,  // ideal (5–8); danach kaum Mehrwert
    ],

    // Entity-Typen, die NIE als Kunden im Portfolio gelten (eigene Ventures = 21).
    'portfolio_exclude_types' => [21],

    /*
    |--------------------------------------------------------------------------
    | Budget Defaults
    |--------------------------------------------------------------------------
    */
    'budget' => [
        'default_limit_cents' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recommendation Engine — Schwellwerte für die Handlungsempfehlungen (P4)
    |--------------------------------------------------------------------------
    */
    'recommendations' => [
        'near_top_min' => 4,            // "knapp außerhalb Top-3" Untergrenze
        'near_top_max' => 10,           // ... Obergrenze (Position 4–10)
        'min_volume' => 200,            // Mindest-Suchvolumen für URL-Empfehlungen
        'thin_word_count' => 600,       // darunter gilt Content als dünn → ausbauen
        'low_backlinks' => 5,           // darunter gilt Backlink-Profil als schwach
        'cluster_coverage_max_pct' => 20,  // darunter: Cluster-Lücke → neue URL
        'cluster_min_keywords' => 3,
        'cluster_min_volume' => 300,
        'quick_win_max_difficulty' => 20,
        'quick_win_min_volume' => 200,
        'quick_win_weak_position' => 20,   // keine/schwache eigene Position darüber
    ],

    /*
    |--------------------------------------------------------------------------
    | DataForSeo Cost Estimates (cents per action)
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Portfolio-Reifegrad — Gates (Schwellen), keine Engine.
    |--------------------------------------------------------------------------
    | Der Reifegrad-Trichter messen→ordnen→verteilen→vertiefen→konvertieren.
    | Die Phase = das erste Gate, das reißt. Opinionated Defaults; hier tweakbar.
    */
    'portfolio_gates' => [
        'ordnungsgrad_min' => 70,   // % Keywords in Cluster, um verteilen zu dürfen
        'durchdringung_min' => 50,  // Ø IST/SOLL % je Cluster für „vertieft"
    ],

    'cost_estimates' => [
        'search_volume' => 5,
        'serp' => 10,
        'labs_suggestions' => 8,
        'labs_ranked' => 10,
        'competitors' => 10,
        'on_page' => 15,
        'backlinks' => 15,
        'backlinks_summary' => 2,
        'llm_mentions' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk-Endpoints — Batchgröße für Kostenschätzung
    |--------------------------------------------------------------------------
    | Manche DataForSEO-Endpoints verarbeiten viele Keywords in EINEM Call
    | (z.B. getSearchVolume: bis 1000 Keywords/Call). Für die hier gelisteten
    | Actions wird der cost_estimates-Wert als Kosten PRO CALL interpretiert
    | (= ceil(count / batch) Calls), nicht pro Keyword. Ohne Eintrag gilt
    | weiterhin die Pro-Keyword-Schätzung.
    */
    'cost_bulk_batch' => [
        'search_volume' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collectors — registered data collectors for the URL pipeline
    |--------------------------------------------------------------------------
    */
    'collectors' => [
        \Platform\Seo\Collectors\KeywordMetricsCollector::class,
        \Platform\Seo\Collectors\GscCollector::class,
        \Platform\Seo\Collectors\SerpRankingCollector::class,
        \Platform\Seo\Collectors\BacklinkCollector::class,
        \Platform\Seo\Collectors\OnPageCollector::class,
        \Platform\Seo\Collectors\PlausibleCollector::class,
        \Platform\Seo\Collectors\LlmMentionsCollector::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline — URL pipeline orchestrator settings
    |--------------------------------------------------------------------------
    */
    'pipeline' => [
        'max_urls_per_run' => 50,
        'max_budget_percentage_per_run' => 25,
        'deprioritize_error_urls' => true,
        'budget_pressure_threshold' => 0.8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Refresh Intervals — base intervals in hours per collector
    |--------------------------------------------------------------------------
    */
    'refresh_intervals' => [
        'keyword_metrics' => 168,    // weekly
        'gsc' => 24,                 // daily
        'serp_ranking' => 168,       // weekly
        'backlinks' => 336,          // bi-weekly
        'on_page' => 336,            // bi-weekly
        'plausible' => 24,           // daily
        'llm_mentions' => 720,       // ~monatlich (teuer, wenig volatil)
    ],

    /*
    |--------------------------------------------------------------------------
    | Daten-Profile — EIN Knopf pro URL. Jedes Profil = {Collector => Kadenz(h)}.
    | Getrennte Leitern für eigene URLs und Wettbewerber (is_own entscheidet).
    | Nur diese Collectoren sind profil-gesteuert; keyword_metrics läuft
    | team-weit weiter (nicht pro URL) und ist NICHT hier gelistet.
    |--------------------------------------------------------------------------
    */
    'profile_collectors' => ['gsc', 'plausible', 'serp_ranking', 'on_page', 'backlinks', 'llm_mentions', 'competitor_footprint'],

    // Collector => cost_estimates-Schlüssel (für die Monatskosten-Projektion).
    'profile_cost_map' => [
        'gsc' => null,                    // gratis
        'plausible' => null,              // gratis
        'serp_ranking' => 'labs_ranked',  // getRankedKeywords je Domain
        'on_page' => 'on_page',
        'backlinks' => 'backlinks',
        'llm_mentions' => 'llm_mentions',
        'competitor_footprint' => 'labs_ranked',
    ],

    'data_profiles' => [
        'own' => [
            'aus'      => [],
            'basis'    => ['gsc' => 24, 'plausible' => 24],
            'standard' => ['gsc' => 24, 'plausible' => 24, 'serp_ranking' => 168, 'on_page' => 336],
            'tief'     => ['gsc' => 24, 'plausible' => 24, 'serp_ranking' => 168, 'on_page' => 336, 'backlinks' => 720, 'llm_mentions' => 720],
        ],
        'competitor' => [
            'aus'        => [],
            'beobachten' => ['competitor_footprint' => 720],
            'analyse'    => ['competitor_footprint' => 720, 'on_page' => 336, 'backlinks' => 720],
        ],
    ],

    // Baseline-Profil, wenn nichts gesetzt (greift auch vor der Migration).
    // Eigene URLs bewusst auf "basis" (gratis): SERP/OnPage sind bewusste
    // Aufwertung je URL/Liste, nicht Default — sonst explodieren die Kosten.
    'data_profile_defaults' => [
        'own' => 'basis',
        'competitor' => 'beobachten',
    ],

    // Boost: SERP-Kadenz (h) und Standard-Dauer (Tage), solange boost_until aktiv.
    'boost' => [
        'serp_hours' => 24,
        'default_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority — default priority values for auto-created URLs
    |--------------------------------------------------------------------------
    */
    'priority' => [
        'own_url_default' => 70,
        'competitor_url_default' => 30,
        'auto_discovered_default' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cluster — Lifecycle, Erfolgs-Quotient & Hygiene (s. docs/CLUSTER-PLAYBOOK.md)
    |--------------------------------------------------------------------------
    */
    'clusters' => [
        // Durchdringung (0–100) = 100 × (w_coverage·Coverage + w_top10·Top10 + w_top3·Top3)
        'penetration_weights' => [
            'coverage' => 0.40,
            'top10' => 0.40,
            'top3' => 0.20,
        ],
        // active → monitored (gut durchdrungen)
        'promote_to_monitored' => [
            'penetration' => 70,
            'top10_share' => 50,   // % der Ziel-Keywords in Top 10
            'hold_snapshots' => 2, // über so viele Snapshots gehalten
        ],
        // monitored → active (Rückfall)
        'demote_to_active' => [
            'penetration' => 60,
        ],
        // active → stalled (Stillstand)
        'stalled_after_weeks' => 8,
        'stalled_min_gain' => 5,   // Punkte Durchdringung, die im Fenster mind. dazukommen müssen
        // Hygiene: nicht-rankende, clusterlose eigene URL gilt ab hier als Ballast
        'url_ballast_days' => 60,
        // Fokus: max. gleichzeitig aktive Cluster je Kunde
        'wip_active_per_customer' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyword Curation — Blacklist Patterns
    |--------------------------------------------------------------------------
    */
    'curation' => [
        'job_patterns' => [
            'stellenangebot', 'stellenanzeige', 'jobs', 'job ', 'karriere',
            'gehalt', 'ausbildung', 'studium', 'beruf werden', 'weiterbildung zum',
            'umschulung', 'quereinsteiger', 'bewerbung', 'vacancy', 'hiring',
            'fernstudium', 'berufsbegleitend',
        ],
        'person_patterns' => [
            'dr. ', 'dr.med', 'prof. ', 'prof.dr',
        ],
        'local_patterns' => [
            'in der nähe', 'in meiner nähe', 'in der naehe',
        ],
        'broker_patterns' => [
            'finden', 'suchen', 'vermittlung', 'verzeichnis', 'empfehlung',
            'buchen', 'terminvereinbarung', 'praxis in',
        ],
        'navigational_patterns' => [
            'arztpraxis', 'klinik ', 'praxis ', 'hautarzt', 'zahnarzt',
            'orthopädie', 'kinderarzt', 'frauenarzt', 'augenarzt', 'hno',
            'krankenhaus', 'mvz ', 'medizinisches versorgungszentrum',
            'online termin', 'ohne termin', 'wartezeit arzt',
        ],
        'cities' => [
            'berlin', 'hamburg', 'münchen', 'köln', 'frankfurt', 'stuttgart',
            'düsseldorf', 'dortmund', 'essen', 'leipzig', 'bremen', 'dresden',
            'hannover', 'nürnberg', 'duisburg', 'bochum', 'wuppertal', 'bielefeld',
            'bonn', 'münster', 'karlsruhe', 'mannheim', 'augsburg', 'wiesbaden',
            'aachen', 'braunschweig', 'kiel', 'chemnitz', 'halle', 'magdeburg',
            'freiburg', 'lübeck', 'oberhausen', 'erfurt', 'rostock', 'mainz',
            'kassel', 'saarbrücken', 'potsdam', 'oldenburg', 'regensburg',
            'heidelberg', 'darmstadt', 'würzburg', 'wolfsburg', 'ulm',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Trends
    |--------------------------------------------------------------------------
    */
    'google_trends' => [
        'enabled' => false,
        'cache_hours' => 168,
    ],
];

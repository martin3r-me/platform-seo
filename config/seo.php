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
    ],

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
    | DataForSeo Cost Estimates (cents per action)
    |--------------------------------------------------------------------------
    */
    'cost_estimates' => [
        'search_volume' => 5,
        'serp' => 10,
        'labs_suggestions' => 8,
        'labs_ranked' => 10,
        'competitors' => 10,
        'on_page' => 15,
        'backlinks' => 15,
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

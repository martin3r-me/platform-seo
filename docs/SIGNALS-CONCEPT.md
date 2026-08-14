# SEO-Signale — Konzept

> Denk-Anker vor dem Bau. Hält den Architektur-Entscheid fest: **SEO bekommt ein
> eigenes, genauso ausgereiftes Signal-System — die *Form* vom Organization-Modul
> geliehen, die *KI* aus `core` geliehen, die *Domäne* selbst besessen.** Kein
> Adoptieren der Org-Signale, keine zweite Brücke nach oben.

Status: **Konzept / beschlossen**, noch kein Code. Stand: 2026-08-13.
Verwandt: [[seo-package-deploy]], `docs/CLUSTER-PLAYBOOK.md`.

---

## 1. Warum eigen statt adoptiert

Das Organization-Modul hat ein ausgereiftes Signal-System (`organization_signal_definitions`
+ `organization_signals` + Inferenz-Schicht + Lifecycle). Die naheliegende Idee wäre,
SEO dort einzuspeisen. **Wir tun das bewusst nicht** — wegen der **Flughöhe**:

| | Org-/VSM-Signal | SEO-Signal |
|---|---|---|
| Linse | Governance / Vitalität einer Entity (VSM) | Operator / Domäne (Rankings, Content) |
| Beispiel | „Kunde X: Cashflow-Risiko" | „URL steht #9 für 2k-Keyword, dünner Content → ausbauen" |
| Auflösung | grob, aggregiert | fein, hebel-konkret |
| Adressat | Steuerung / Leitung | die tägliche SEO-Arbeit |

Das Operative in den VSM-Posteingang zu kippen, ertränkt ihn in Rauschen oder erzwingt
verlustige Aggregation. SEO-Signale hängen zudem an **sehr spezifischem Domänenwissen**
und münden in **konkrete Handlungsempfehlungen** (technisch **und** inhaltlich) neben
den Clustern. Deshalb: **exklusiv im SEO-Modul.**

## 2. Die Brücke nach oben — die bestehende, keine zweite

SEO liefert schon heute Entity-Metriken an Org über
`Platform\Seo\Organization\SeoEntityLinkProvider` (`implements HasMetricDefinitions`) —
die „7½ Dimensionen". Darin stecken bereits signal-abgeleitete Werte
(`seo_signals_new_7d`, `seo_recommendations_open`, kritisch offen, Redirects).

**Regel:** Wenn das reife Signal-System eine *aggregierte Gesundheit* nach oben spielen
soll, wird das zu **reicheren Metriken auf genau diesem Provider** — nicht zu einem
neuen Signal-Push. Ein destilliertes Bild pro Kunde („SEO-Sichtbarkeit fällt · N
kritische offen"), nie die Feuerwehr aller Einzel-Hebel.

- **Jetzt:** alles SEO-intern. Die Brücke trägt nur aggregierte Kennzahlen (Bestand).
- **Später:** einzelne Signale *gezielt aussteuern* (z. B. in Planner/Flynk) — bewusst
  vertagt, nicht Teil der ersten Ausbaustufe.

## 3. Muster-Taxonomie (domänennativ)

Signale entstehen aus **Bewegung** (Snapshot → Snapshot) und Bestand, nicht aus
hartcodierten if-Zweigen. Muster-Typen (angelehnt an Org: threshold / trend /
cross_dimension / ratio):

| Muster | Typ | Quelle | Typischer Hebel |
|---|---|---|---|
| `striking_distance` | threshold | Position 4–10 & Volumen ≥ X | URL ausbauen (Content/Backlinks) |
| `position_drop` / `position_gain` | trend | Δ Position über Snapshots | Ursache prüfen / halten |
| `cannibalization` | cross_dimension | ≥2 eigene URLs, gleiches Keyword | konsolidieren / entflechten |
| `thin_content` / `content_gap` | threshold | Wortzahl / Abdeckung vs. Nachfrage | **Content-Brief** (inhaltlich) |
| `lost_ranking` / `lost_snippet` | trend | Ranking/Snippet verloren | zurückerobern |
| `cluster_underperformance` | ratio | Durchdringung eines Clusters fällt | Cluster bespielen (→ Playbook) |
| `decay` | trend | `visitors_30d` schleichend rückläufig | Refresh / Relaunch |

## 4. Die Arbeitskette — ein Signal ist der *Anfang*, kein Alarm

```
Signal ──► Empfehlung (AI-priorisiert nach echtem Wert)
   │           │
   │           ├─► Änderungsanforderung · technisch  (URL-Fix, Redirect, Meta)
   │           └─► Änderungsanforderung · inhaltlich (Content-Brief — Objekt existiert)
   │
   └─► Bezug:  Cluster (thematisch)  │  URL / Liste (konkret)

Lifecycle:  offen → quittiert → in Arbeit → gelöst
                                              └─ idealerweise durch BEWEGUNG bestätigt
                                                 (Position erholt → Signal löst sich selbst)
```

- **Content-Brief** (`SeoContentBrief` + `…Section/Revision/Note/Link`) ist das bereits
  existierende Zielobjekt für inhaltliche Änderungen.
- **Cluster** und **Änderungsanforderung** sind die zwei Arbeits-Achsen neben dem Signal.
- **Auflösung durch Bewegung**: ein Signal gilt als gelöst, wenn die Metrik, die es
  ausgelöst hat, sich erholt — nicht (nur) durch manuelles Abhaken. Das macht die
  Bewegung/Verlauf-Sicht zum Herzstück, nicht zum Nebentab.

## 4a. Worüber ein Signal läuft — zwei Achsen

Eine Definition trennt sauber:

- **Muster** = wonach gesucht wird.
- **Population** = die Menge, über die ausgewertet wird: **ganzes Portfolio · Entity(+Subtree)
  · Liste · [Cluster, später]** (`scope_type` + `scope_value`).

**Grundprinzip: keine zwei Welten vermischen.** „Ganzes Portfolio" (`all`) ist nicht der rohe
Team-URL-Pool, sondern nur das **bewusst zusammengestellte Arbeitsset** — eigene URLs, die an
einem Org-Knoten hängen *oder* in einer Liste sind. Unzugeordnete Importe (Ablage, z. B. ein
separates Produkt wie Syltjunkie) bleiben draußen, bis sie einem Kunden oder einer Liste
zugeordnet werden. Erst dann laufen sie im normalen Fluss (Signale, Push, Dashboard) mit.

**Beschluss: eine Population pro Definition.** Zwei Arenen = zwei Definitionen
(z. B. „Caterer-Kannibalisierung" *und* „Foodpol-Kannibalisierung"), nebeneinander,
explizit und nachvollziehbar. Kombiniert wird über mehrere Definitionen, nicht in einer.

**Die nicht-offensichtliche Einsicht:** Bei **relationalen** Mustern (Kannibalisierung,
Cluster) ist die Population **keine Filter, sondern die Methode** — sie zieht die Arena
„wer konkurriert mit wem". Dieselbe Kannibalisierung über die Caterer-**Liste**
(markenübergreifend) vs. über den Foodpol-**Subtree** (innerhalb der Holding) ergibt
**zwei verschiedene, beide richtige Signale**.

**Granularität & Anheftung (wo das Signal landet, wie es hochrollt):**

| Muster | läuft pro | Signal hängt an | rollt hoch via |
|---|---|---|---|
| striking_distance, position_drop, thin_content, decay | URL (× Keyword) | **URL** | URL→Entity (Provider) |
| cannibalization | Keyword *in der Arena* | **Keyword** (+ Kontext: Arena, konkurrierende URLs) | Arena-Knoten |
| cluster_underperformance | Cluster | **Cluster** (+ Pillar-URL) | Cluster→Entity |

**v1-Muster-Set:** `striking_distance`, `position_drop`, `thin_content` (pro-URL, über
Entity+Subtree *und* Liste) + `cannibalization` (zuerst über Listen). Cluster-Muster vertagt.

## 4b. Routing der Arbeitskette (implementiert)

Zentrale Klassifikation `SeoSignalRouting`: Muster → Änderungsart → Ziel.

| Änderungsart | Muster | Ziel |
|---|---|---|
| `content` | thin_content, content_gap, cluster_underperformance | **Content-Brief** (SEO-intern) |
| `page_edit` | striking_distance, position_drop, lost_ranking, decay | **Flynk-Aufgabe** |
| `structural` | cannibalization | **Flynk-Aufgabe** (Konsolidierung/301) |

- **Content** → `SeoSignalDispatcher` erzeugt einen `SeoContentBrief` (Sections aus dem
  KI-`brief_outline`, Instruktion aus recommendation+steps). Idempotent über `context['work']`.
- **page_edit/structural** → fließen über die **bestehende** Flynk-Brücke: der
  `SeoFlynkContextProvider` pusht die Empfehlungen (jetzt inkl. definition-getriebener Signale)
  mit `change_kind`, `target`, KI-`recommendation`/`steps` und stabiler `ref` (Signal-uuid).
  **Prinzip: SEO ruft Flynk nie selbst.** Die ausgehende Task-Erzeugung auf Flynk-Seite ist der
  letzte offene Meter (abhängig davon, wie Flynk Pushes zu Aufgaben macht).
- Reihenfolge: **evaluate → enrich → dispatch** (`seo:dispatch-signals`).

## 4c. Governance — gesteuerter Vorrat, Vorfahrt für Notfälle

Signale sind ein **Arbeitsvorrat**, keine Flut. Der Evaluator sammelt Kandidaten, reiht
sie nach Impact und lässt nur bis zu zwei Grenzen durch:
- **WIP-Limit** (`seo.signals.wip_limit`, Default 5) — max. gleichzeitig offen.
- **Tageslimit** (`daily_new_limit`, Default 3) — max. neue/Tag; Nachschub erst, wenn
  offene erledigt sind.

**Ausnahme: `severity = critical` umgeht beide Limits** — eine kaputte Kundenseite (5xx,
Redirect) darf nicht hinter „URL ausbauen" warten. Dedup gilt weiter. Damit: gesteuerter
Fluss fürs Tagesgeschäft, sofortige Vorfahrt für echte Notfälle.

## 5. Die Form (Datenmodell) — von Org geliehen, SEO-eigen

Eigene SEO-Tabellen, aber die bewährte Form:

- **`seo_signal_definitions`** — deklarative Muster: `pattern_type`, `conditions` (JSON),
  `scope` (all / entity_type / subtree / liste), `frequency` (every_snapshot / daily),
  `severity`, `is_active`.
- **`seo_signals`** (erweitert vom Bestand) — Instanz: `status` (offen / quittiert /
  in_arbeit / gelöst / verworfen / schlummernd), `severity`, `title`+`message`,
  `trigger_metrics` (JSON), Bezug auf `url_id` **und** ableitbare `entity_id`,
  `resolved_at` / `resolved_by`, `resolved_by_movement` (bool).
- **Lifecycle drumherum** (nach Bedarf): Kommentare, verknüpfte Änderungsanforderungen,
  Snooze.

> Der Bestand (`seo_signals`: `signal_type/severity/title/status/context`, an `url_id`,
> regelgeneriert von `SeoRecommendationService`) ist die Keimzelle — wird erweitert, nicht
> ersetzt. Migrationspfad statt Neubau.

## 6. AI-Schicht — geliehen aus `core`, nicht aus Org

Die KI kommt **nicht** aus dem Org-Modul (dessen Inferenz-Orchestrierung ist org-lokal),
sondern aus dem plattformweiten Dienst: `Platform\Core\Services\OpenAiService` /
`LLMProviderRegistry` / `LLMProviderContract`.

Neu zu bauen ist damit nur ein **dünner SEO-Inferenz-Runner**: ein Prompt bewertet
Bewegung + Keyword-/Content-Kontext eines Signals und liefert (a) die **wertbegründete
Priorisierung** und (b) optional einen **Content-Brief-Entwurf**. Die KI selbst, die
Snapshots/Bewegung und die Signal-Form sind Bestand.

## 6a. Zwei Sparten: berechnet vs. KI-gestützt

Die KI bleibt in einer **klar abgegrenzten Spur**, nicht diffus überall. Am Datenmodell
sichtbar über `engine` (an der Definition) und `enrich_with_ai`.

**Sparte A — Berechnete Signale** (`engine = computed`): deterministisch aus Schwellen/
Relationen (der Evaluator, Schritt 2). Das verlässliche Gerüst.

**Sparte B — KI-gestützt**, drei Rollen:

1. **Anreicherung** (`enrich_with_ai = true` an einer berechneten Definition) — **gebaut,
   Schritt 4**: `SeoSignalEnrichmentService` nimmt ein gefeuertes Signal + Kontext und lässt
   die generative KI (core's `LLMProviderRegistry`) eine konkrete Handlungsanweisung schreiben
   (+ optional Content-Brief-Umriss). Ergebnis in `signal.context['ai']`. Command-getrieben
   (`seo:enrich-signals`, `--limit`) wegen LLM-Kosten. Das berechnete Signal bleibt das Gerüst,
   die KI schreibt das „wie".
2. **KI-native Signale** (`engine = ai`) — *später (Rolle 2)*: Muster, die Regeln nicht sehen
   (semantische Kannibalisierung, Content-Gap vs. Intention, Marken-/Tonbruch). Der LLM bewertet
   statt einer Schwelle.
3. **Synthese** — *später (Rolle 3)*: KI liest alle offenen Signale eines Kunden und schreibt die
   priorisierte „Was jetzt"-Empfehlung.

KI kommt aus `core` (`LLMProviderRegistry` → `OpenAiProvider`/`AnthropicProvider`), kein eigener
KI-Stack.

## 7. Priorisierung — der Fix für „Foodpol: abstellen statt ausbauen"

Heute reiht die Engine rein nach `severity`; `RETIRE_URL` (`watch`) schlägt bei
Gleichstand `EXPAND_URL` (`watch`), Tiebreak (`metric_delta`) ist bei beiden 0 → die
schwächste Aussage gewinnt. **Ziel:** Reihung nach **Wert**, nicht Severity —
Aktions-Wert (ausbauen > backlinks > neuer Content > abstellen) × echtem Impact
(Suchvolumen × Nähe zu Top-3 × Bewegungsstärke), von der AI-Schicht begründet.

## 8. Was jetzt NICHT

- Kein Adoptieren der Org-Signale, kein Schreiben in `organization_signals`.
- Keine zweite Brücke — nur der bestehende `HasMetricDefinitions`-Provider.
- Kein gezieltes Aussteuern in Planner/Flynk — später.

## 9. Parallel & entkoppelt: Freshness + manuelles Update

Braucht die Signal-Architektur nicht und schafft sofort Vertrauen: Freshness (letzter/
nächster Fetch) **auf URL- und Listen-Ebene** sichtbar, plus **„Jetzt aktualisieren"**
pro URL/Liste (gezielter Pipeline-Job, wie der Cluster-Discovery-Button).

## 10. Offene Punkte / nächste Schritte

1. Reihenfolge: **Freshness + manuelles Update** zuerst (schnelles Vertrauen, entkoppelt),
   dann der Signal-Umbau — oder direkt in die Signal-Architektur.
2. Muster-Set der ersten Ausbaustufe festlegen (Vorschlag: `striking_distance`,
   `position_drop`, `cannibalization`, `thin_content` — die vier mit dem klarsten Hebel).
3. Signal-Definitions als Config oder als DB-Objekte (bearbeitbar in der UI)?
4. Content-Brief als Standard-Ausgang für `thin_content`/`content_gap` verdrahten.

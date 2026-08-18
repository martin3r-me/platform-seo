# NORDSTERN v2 — Antwort-Kontrolle (Answer Control System)

> v1 (docs/NORDSTERN.md) hat die **Strategie-Ebene** richtig gemacht: URL · Wirkungsraum · Cluster,
> Reifegrad, Maßnahmen-Posteingang. v2 wirft das **nicht weg** — es re-zentriert alles um eine
> neue Atomeinheit, weil die alte (Keyword → Seite → Position) stirbt.

---

## 1 · Warum v2 — der Bruch
Alte SEO-Tools (unseres bisher inklusive) modellieren **Keyword → Seite → Position**. Drei Verschiebungen machen das tot:

1. **Content ist gratis** (LLMs schreiben ihn). Knapp ist nicht *produzieren*, sondern **wissen was · wahr/autoritativ sein · differenziert sein**.
2. **Suche → Antwort.** AI Overviews, ChatGPT, Perplexity *antworten* synthetisch. „Position 3" ist wertlos, wenn die Maschine eine Antwort baut. Der Gewinn: **die zitierte Quelle sein.**
3. **Das Web wird von Agenten gelesen.** Die „Seite" zerfällt in **maschinen-konsumierbare Antwort-Einheiten** (Entitäten, Fakten, strukturierte Claims).

**Konsequenz:** Wir bauen kein SEO-Tool. Wir bauen ein **Antwort-/Autoritäts-Kontrollsystem** — entitäts-first, multi-surface.

---

## 2 · Die neue Atomeinheit: die Antwort-Einheit
```
Entität / Frage        (Nachfrage)   das WAS gefragt/gewusst wird — nicht das Keyword
   └─ Antwort-Einheit  (Asset)       der atomare, strukturierte Antwort-Baustein, den WIR besitzen
        └─ Präsenz     (Messung)     je Surface: klassische SERP · AI-Overview · ChatGPT/Perplexity · KG
```
- **Seite = Behälter** von Antwort-Einheiten (nicht mehr das Atom).
- **Wirkungsraum = Portfolio besessener Entitäten + Antworten.**
- **Cluster (v1) ≈ Entität/Themen-Bündel** — der Haken ist schon da.

---

## 3 · Die Spine (Datenmodell v2)

**Entity** (`seo_entities`) — das Nachfrage-Atom
- name, typ (Frage · Produkt · Marke · Ort · Konzept), aliase, embedding
- Nachfrage je Surface: search_volume, ai_ask_volume (wie oft KI-gefragt)
- verknüpft mit Cluster (v1) — Cluster wird zum Entity-Bündel

**AnswerUnit** (`seo_answer_units`) — unser Asset
- entity_id, url_id (Host-Seite), owner (Property/Rolle)
- claim/answer (der Kern), evidence (Quelle/Beleg), schema_type (FAQ/HowTo/Product…)
- freshness (last_verified_at), status (entwurf · live · veraltet)
- content_hash / version (→ IST-Historie)

**Presence** (`seo_answer_presence`) — die Messung, je Surface × Zeit
- answer_unit_id (oder entity_id), surface (serp · ai_overview · chatgpt · perplexity · knowledge_panel)
- present (bool), position/rank, cited (bool), citation_url, share_of_answer (%)
- checked_at
- → **Share of Answer** = wie viel des Antwort-Raums einer Entität uns gehört vs. Wettbewerb

**Experiment** (`seo_answer_experiments`) — der Lern-Loop je Antwort
- answer_unit_id, measure_id (→ Posteingang), brief_id (→ SOLL)
- hypothesis, change_summary, applied_at
- baseline (Presence-Snapshot vorher), control_set (ähnliche unveränderte Antworten)
- result (Presence-Snapshot nachher, nach N Wochen), verdict (worked · flat · hurt), learning
- **A/B-Ehrlichkeit:** kein Split-Test (Google zeigt eine Version) — **kontrolliertes Vorher/Nachher + Kontrollgruppe**, um Effekt vom Ranking-Rauschen zu trennen.

**Wie v1 andockt:**
- `SeoContentBrief` (+ Sections/Revisions) = die **SOLL-Spezifikation** einer AnswerUnit.
- `SeoPortfolioMeasure` „brief_existing" = die **Strategie**, die ein Experiment auslöst.
- `SeoUrl` = **Container** (behält Metriken); die AnswerUnits hängen darunter.
- `SeoPageChange` = passive **Änderungs-Erkennung** (füttert Experiment-Ergebnisse).

---

## 4 · Der Loop auf Antwort-Ebene (spiegelt den WR-Posteingang)
```
Maßnahme (Strategie)  →  Brief (SOLL-AnswerUnit)  →  IST-AnswerUnit + Gap
   →  Experiment (Baseline-Presence)  →  Flynk produziert  →  N Wochen
   →  Presence-Messung (multi-surface)  →  Verdict + Learning  →  bleibt als Kontext
```
Jede „optimieren"-Maßnahme wird ein **messbares Experiment über Surfaces** — nicht „Wortzahl hoch, hoffen".

---

## 5 · Der Burggraben (Nordstern, konkret)
Über Jahre akkumuliert jeder Wirkungsraum einen **Graph besessener Entitäten + verifizierter, frischer, maschinen-lesbarer Antworten**. *Das* ist „über Jahre in vielen Gebieten organische Reichweite kontrollieren" — nicht 1.000 rankende Keywords, sondern **die autoritative Antwort auf N Entitäten besitzen**, quer über klassische Suche *und* KI. Kein Wettbewerber hat diesen akkumulierten Antwort-Graphen + das Entscheidungs-/Experiment-Gedächtnis.

---

## 6 · DATEN — was müssen wir sammeln? (die ehrliche Analyse)

**Was wir heute sammeln:** DataForSEO (Keywords, SERP-Positionen, On-Page shallow, Backlinks) · GSC · Plausible · LlmMentions (grober KI-Mention-Zähler) · Keyword-Metriken.

**Kern-Erkenntnis:** Wir brauchen **kaum neue Anbieter — sondern anderes/reicheres Abrufen** aus erreichbaren Quellen. Vier Lücken:

1. **Content-Extraktion (IST) — die größte Lücke.** Heute nur Titel/H1/Headings/Wortzahl. Wir brauchen den **echten Seiteninhalt → in AnswerUnits zerlegt** (Sektionen/Entitäten/Q&A/Schema). Neuer Collector: fetch + Readability + LLM-Extraktion. Gilt auch für **Top-Wettbewerber-Seiten** (→ Gap: welche AnswerUnits decken die ab, die uns fehlen).
2. **SERP-Features + AI-Overview-Zitate.** Nicht nur Position — sondern: gibt es ein AI-Overview? Featured Snippet? PAA? **Wer wird im AI-Overview zitiert?** DataForSEO liefert SERP-Features + AI-Mode; das ziehen wir schon *fast* (FetchSerp) — muss um Features/AI-Overview-Quellen erweitert werden.
3. **Aktives KI-Zitat-Probing.** Die Antwort-Maschinen (ChatGPT/Perplexity/Google-AI) **je Entität/Frage aktiv fragen** und protokollieren, *ob & wo wir zitiert* werden = der neue „Rank-Check". LlmMentionsCollector ist der Haken — muss von „Mention-Zähler" zu **per-Frage-Zitat je Engine** werden.
4. **Struktur-Daten / Knowledge-Graph.** Schema.org-Abdeckung unserer Seiten (fällt aus Content-Extraktion #1 ab, gratis) + ist die Entität im Google-KG (leichter KG-Check).

**Ein evtl. genuin neuer Baustein:** eine dedizierte **KI-Sichtbarkeits-Quelle** (DataForSEO-AI-Endpoints *oder* direkte Perplexity/OpenAI/Gemini-Abfragen). Aber selbst das ist „andere Daten aus erreichbaren APIs", kein neuer Vendor-Zoo.

**Kurz:** mehr **Tiefe + andere Endpoints**, nicht mehr Anbieter. Der eine echte neue Collector = **Content→AnswerUnit-Extraktion** (eigene Seiten + Wettbewerber). Der eine echte Umbau = **LlmMentions → per-Entität-Zitat über Engines**.

---

## 7b · Der Endpunkt: agent-actionable Register (know → trust → transact)
Die eigentliche Zukunft ist nicht „zitiert werden" — es ist **durch uns handeln lassen**. Ein KI-Agent, der etwas über Sylt wissen will, landet bei uns (Discovery) → vertraut unserer Antwort (Trust) → und **bucht ein paar Jahre weiter die passende Ferienwohnung / das Catering-Event direkt über uns** (Transaction). Der Bogen:

```
Discovery (Answer-Presence)  →  Trust (Autorität)  →  Transaction (Agent bucht über uns)
        └──────────────── das Register spannt alle drei ────────────────┘
```

**Model-Erweiterung — die Entität trägt nicht nur Wissen, sondern Handlung:**
```
Entity
 ├─ AnswerUnit      (Wissen — was wir autoritativ beantworten)
 ├─ Offer/Availability (Angebot — Preis, Verfügbarkeit, Konditionen)   ← neu, für Transaction
 └─ Action          (buchbar — ReserveAction/OrderAction, Agent-Endpoint) ← neu
```

**Kein Sci-Fi — die Bausteine sind da:**
- **schema.org Actions** (ReserveAction, OrderAction) + kommende agentic-commerce-Standards (OpenAI/Google) machen Seiten agent-*handelbar*, nicht nur agent-*lesbar*.
- **Unsere Plattform ist schon MCP-nativ** — das Register ist *heute intern* agent-aufrufbar (`syltjunkie.entities.GET`, `entity_urls`, …). Der Schritt: nach außen öffnen + **Offer/Availability/Buchung** dranhängen.
- Syltjunkie hat die **Supply** (2.753 Entitäten: Ferienwohnungen, Restaurants, Events) → ein **agent-nativer Buchungs-Layer für eine ganze Region**, von Tag 1 maschinen-adressierbar.

**Damit wird das Register das Kron-Asset — nicht das SEO-Tool.** SEO/Answer = *wie Agenten uns finden*; Register + Buchung = *wie sie transagieren*. Der Burggraben ist zweiseitig: der autoritative Antwort-Graph **und** der buchbare Inventar-Graph = wir sind der Default-Fulfillment für ein Thema/eine Region.

**Konsequenz für JETZT:** die Entity/AnswerUnit-Spine bekommt von Anfang an einen **Platz für Offer/Action** (nullable, ungenutzt bis später). Wir bauen die Buchungs-Engine *nicht* heute — aber wir modellieren so, dass sie **ohne Rebuild** reinrutscht. Discovery zuerst scharf; Transaction Jahre raus, aber die Spine ist von Tag 1 transaktions-fähig gedacht.

## 7 · Offene Sequenzierung (Entscheidung nötig)
Das Modell trägt **beide** Surfaces. Was wir *zuerst* scharf machen, hängt am BHG-Wert **heute**:
- **Klassische SERP-Präsenz** für Kunden (Catering & Co) — wenn dort heute das Geld liegt.
- **KI-Antwort-Präsenz (GEO)** — wenn wir die Wette auf die nahe Zukunft zuerst spielen.

Empfehlung: Modell für beide bauen, **Messung zuerst dort, wo BHG heute Umsatz macht**, KI-Präsenz parallel als Frühindikator mitlaufen lassen.

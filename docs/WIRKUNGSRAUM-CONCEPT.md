# Wirkungsraum — Steuer-Scope für Verbund-Sichtbarkeit

> Nordstern: ein Tool, das **KI-getrieben die Sichtbarkeit erhöht** — nicht je
> Einzelseite, sondern klug ausgesteuert über einen **Verbund** kontrollierter
> Properties, hin zur **maximalen gemeinsamen Sichtbarkeit**.

## Die zwei Grundhaltungen

| | **Liste** | **Wirkungsraum** |
|---|---|---|
| Haltung | **Beobachten** | **Steuern** |
| Mitglieder | beliebige URLs (auch Wettbewerber) | nur **kontrollierte** (eigene) URLs |
| Ziel | keins | **definiert** (welche Themen dominieren) |
| Signale | „schau, das bewegt sich" | „so verteilen, so verlinken" |
| Anspruch | wissen | handeln/optimieren |

**Steuern = Kontrolle × Ziel.** Fehlt Kontrolle → nur beobachten (Liste). Fehlt
das Ziel → nur messen, nicht aussteuern.

## Warum eigene Entität (nicht Org-Rolle, nicht Liste)

- Gilt auch für **Kunden-Gruppen** — die stehen nicht in *unserem* Org-Baum.
- Die Zusammenstellung ist **SEO-getrieben** (Thema/Keyword-Overlap), darf quer
  zum Org-Baum liegen.
- **Rückbericht ≠ Komposition:** der Org-Baum ist wohin wir berichten
  (Rollup via Dimension-Link, Alias `seo_wirkungsraum`) + Ziel-Quelle für
  eigene Ventures — aber nicht der Bauplan.

→ Komposition lebt **hier im SEO-Modul**. Liste bleibt die reine Beobachtungs-Lupe.

## Cluster = die Strategie-Einheit

- **Neuaufbau:** Cluster bewusst planen (ein Thema → eine Owner-URL).
- **Bestand (wild rankend):** rankende Keywords **clustern** (`clusters.auto.POST`,
  SERP-Overlap) → die entstehenden Cluster *sind* die faktische Strategie.
- **Cluster gehört zur URL** (Owner via `pillar_url_id`) — nicht zur Liste.
- **Keine gemeinsamen Cluster** (= Kannibalisierung by design). Ein gemeinsames
  Thema wird in differenzierte Einzel-Owner-Cluster **aufgeteilt** und
  **untereinander verlinkt** → der Verbund dominiert gemeinsam, ohne sich zu beißen.

## Komponierbarkeit (der Clou)

Rekursiv dasselbe Muster auf jeder Ebene — „überlappt oder komplementiert?":

```
Owner-Cluster  →  Wirkungsraum  →  Verbund (gruppierte Wirkungsräume)
   Cluster↔Cluster      WR↔Cluster        WR↔WR
   Überlapp = Kannibalisierung → auflösen
   Komplement = Koordination → verlinken
```

## Rollen-Modell (wer macht was)

```
URL          = Basis        → Rohdaten + atomarer Plan (welche Cluster besitzt diese Seite)
Liste        = Beobachten   → Lupe, nur Info-Signale (kein Handlungs-Anspruch)
Wirkungsraum = Handeln       → bewerten, Signale → Aktion, Handlung triggern (= der Arbeitsraum)
Org-Baum     = Berichten     → Rollup / Identität (wohin melden)
```

- **Der Wirkungsraum IST der Arbeitsraum.** Ein Kunde ist im einfachsten Fall
  EIN Wirkungsraum. Skaliert von *einer* URL (Solo-Property, Verbund von eins —
  interne Seiten/Verlinkung gelten trotzdem) bis *ganzer Konzern-Verbund*. Der
  firmenübergreifende Layer ist optional.
- **Signale entstehen aus den Daten (URL/Cluster), werden aber nur im
  Wirkungsraum zur Handlung** (Kontrolle × Ziel). Auf einer Liste bleibt dasselbe
  Signal bloße Beobachtung.
- **Planung ist zweistufig:** atomar an der URL (Seite → Cluster), strategisch
  im Wirkungsraum (wer besetzt was, kein Overlap).

## Zustandsmodell (die 5 Facetten eines Wirkungsraums)

Die KI liest genau diese fünf, um die Verteilung zu triggern:

| Facette | Was | Ableitung |
|---|---|---|
| **Mitglieder** | eigene/kontrollierte URLs | Membership (steuern) |
| **Cluster** | die Themen des Verbunds | Aggregat der Mitglieds-Cluster |
| **Durchdringung** | wie *tief* besetzen wir jeden Cluster (Abdeckung × Position) | `coverage_pct` je Cluster, aggregiert |
| **Wettbewerber** | der Benchmark | Vereinigung der Wettbewerber der Mitglieder (abgeleitet, nicht Mitglied) |
| **Ungeclusterter Rest** | loser Fußabdruck / Backlog (mit Volumen-Filter) | `cluster_id IS NULL` |

Steuer-Logik daraus: hoch durchdrungen + kein Overlap → verteidigen · dünn +
Potenzial → Chance · zwei Mitglieder im selben Cluster → Kannibalisierung ·
Wettbewerber durchdringt tiefer → Lücke schließen · ungeclustert mit Volumen →
clustern → neues Thema. **Zeitlich:** steigende Durchdringung bei fallenden
Wettbewerbern = die kollektive Entwicklung, sichtbar gemacht.

## Bausteine

**Vorhanden:** `clusters.auto.POST` (Bestand ordnen) · `pillar_url_id` (Owner-Seite) ·
Kannibalisierungs-Analyse (Listen) · Brief-Links (Verlinkung).

**Fehlt — die KI-Klammer:** aus (geclusterten Rankings + Owner-Zuordnung +
Kannibalisierung) die **Verteilung** vorschlagen: „splitte dieses Thema so,
verlinke so → der Verbund holt das Maximum."

## Roadmap (Slices)

Leitprinzip: die Analyse (Aggregat-KPIs, Wettbewerber, Kannibalisierung) ist
URL-Set-abgeleitet — **Liste und Wirkungsraum teilen diesen Kern**, unterscheiden
sich in Haltung (beobachten/steuern) + den Steuer-Facetten. Also **teilen, nicht
duplizieren**. Listen zuletzt abwandeln, damit der laufende Betrieb nicht bricht.

- **Slice 1 (gebaut):** Entität `seo_wirkungsraeume` (+ URL-Pivot, `parent_id`,
  `goal`), Org-Alias, Tools `seo.wirkungsraeume.POST/GET` +
  `seo.wirkungsraum_urls.POST` (nur eigene URLs). Bootstrap 1:1 aus Liste.
- **Slice 2 — auf Listen-Niveau + UI:** Index/Detail (Aggregat-KPIs, Mitglieder,
  Wettbewerber-Tab, Kannibalisierung — Listen-Logik wiederverwenden). PLUS das,
  was der Listen-UI fehlt: **Mitglieder-Management in der UI** (URLs zufügen/lösen).
- **Slice 3 — die Steuer-Facetten:** Durchdringung (coverage-Aggregat + vs.
  Wettbewerber) · ungeclusterter Rest (Volumen-Filter) · Cluster-Owner
  (`pillar_url_id` sauber setzen) · Verbund-Sichtbarkeit über Zeit.
- **Slice 4 — die KI-Klammer:** aus den 5 Facetten die Verteilung vorschlagen
  (Themen splitten, Owner zuweisen, cross-linken, entkannibalisieren). Plus
  WR↔WR-Analyse (Verbund-Ebene).
- **Slice 5 — Listen abwandeln:** auf reine Beobachtung reduzieren (keine
  Handlungs-Affordances/Fix-Signale; nur Datenfluss + Trends + Wettbewerber).

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

## Bausteine

**Vorhanden:** `clusters.auto.POST` (Bestand ordnen) · `pillar_url_id` (Owner-Seite) ·
Kannibalisierungs-Analyse (Listen) · Brief-Links (Verlinkung).

**Fehlt — die KI-Klammer:** aus (geclusterten Rankings + Owner-Zuordnung +
Kannibalisierung) die **Verteilung** vorschlagen: „splitte dieses Thema so,
verlinke so → der Verbund holt das Maximum."

## Umsetzungsstand (Slices)

- **Slice 1 (gebaut):** Entität `seo_wirkungsraeume` (+ URL-Pivot, `parent_id`
  Verschachtelung, `goal`), Org-Alias `seo_wirkungsraum`, Tools
  `seo.wirkungsraeume.POST/GET` + `seo.wirkungsraum_urls.POST` (nur eigene URLs).
- **Nächste Slices:** UI (Index/Detail) · Ziel = Cluster-Zuordnung (target themes)
  · Verbund-Sichtbarkeit über Zeit · WR↔WR-Analyse · **KI-Verteilungs-Empfehlung**.

# Content-Brief-Tracking (schlankes Modell)

Ziel: verfolgen, **ob** ein geplanter Brief live ist — nicht, was in der Produktion
(Flynk/Kunde) passiert. Zwei Ebenen, sauber getrennt:

- **Umsetzung** — ist die Seite gebaut? (Marker im `<head>`)
- **Erfolg** — wirkt sie? (Ranking, misst das Modul ohnehin)

## Der einzige Vertrag: der Marker

Die veröffentlichte Seite trägt im `<head>` einen opaken Marker mit der Brief-UUID
(kein PII, keine Strategie):

```html
<meta name="x-content-brief" content="{brief-uuid}">
```

Die UUID steht am Brief (`SeoContentBrief::uuid`, im UI/GET als `marker_meta`
fertig ausgegeben) — **und ist identisch mit dem `ref`, den der Flynk-Connector
beim Kontext-Push je Content-Brief mitschickt.** Mehr braucht es von der
Flynk-/CMS-Seite nicht: keine Feedback-API, keine Task-/Page-IDs.

## Der Loop (crawl-basiert)

`seo:reconcile-briefs` (bzw. `seo.content_briefs.reconcile.POST`) holt für jeden
offenen Brief die `target_url` per leichtem HTTP-Fetch — **kein DataForSeo-Cost** —
und liest den Marker. Stimmt die UUID:

1. Brief → Status `published`, `published_url` + `published_at` gesetzt,
2. die Seite wird als eigene, getrackte `seo_url` registriert → Ranking-Tracking
   läuft ab jetzt auf der Live-Seite.

Marker-basiert → übersteht Slug-Änderungen, braucht keine aktive Rückmeldung.
Empfehlung: täglich schedulen.

## Die Quote im Cluster

Aus den `published`-Briefs ergibt sich pro Cluster die **Umsetzungs-Quote**
(veröffentlichte Briefs / Briefs gesamt) — sichtbar in Cluster-Liste und -Detail.
Das ist der Produktions-Fortschritt; der eigentliche Erfolg folgt übers Ranking
(coverage_pct, Sichtbarkeit, Top-10 je Cluster).

## Bewusst NICHT gebaut

Kein Reverse-Feedback-Kanal, keine `external_task_ref`/`external_document_ref`-
Pflege. Der Prozess in Flynk ist eine Blackbox — uns interessiert nur das Ergebnis
im `<head>`. (Die Felder existieren nullable aus einer früheren Ausbaustufe und
sind ungenutzt; können bei Bedarf entfernt werden.)

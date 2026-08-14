# Content-Brief-Tracking (SEO ↔ Flynk-Loop)

Ziel: einen Content-Brief von der Strategie (SEO) über die Produktion (Flynk/Kunde)
bis zur veröffentlichten, getrackten Seite lückenlos verfolgen — ohne enge Kopplung.

Zwei Referenzen sichern sich gegenseitig ab:

## A) Vorwärts-Referenz — Flynk-IDs am Brief

Bei der Übergabe an Flynk (Task-Erzeugung über den Flynk-Connector — *SEO ruft Flynk
nie selbst*) werden die zurückgegebenen IDs am Brief gespeichert:

| Feld (seo_content_briefs) | Bedeutung |
|---|---|
| `external_project_ref`  | Flynk-Projekt |
| `external_task_ref`     | Flynk-Aufgabe (Typ new_page/page_edit) |
| `external_document_ref` | Flynk-Dokument |

Setzen via `seo.content_briefs.PUT` und Status auf `queued`.

## B) Rückwärts-Verifikation — Provenance-Marker im `<head>`

Die veröffentlichte Seite trägt einen opaken Marker (kein PII, keine Strategie):

```html
<meta name="x-content-brief" content="{brief-uuid}">
<meta name="x-flynk-document" content="{document-uuid}">   <!-- optional -->
```

Die brief-uuid steht am Brief (`SeoContentBrief::uuid`, im UI/GET als `marker_meta`
fertig ausgegeben). **Das ist der einzige Teil, den die Flynk-/CMS-Seite umsetzen
muss:** den Marker beim Rendern der Seite in den `<head>` schreiben.

## Status-Kette

```
briefed → queued (an Flynk übergeben) → in_production → published
```

## Der Loop schließt sich

`seo:reconcile-briefs` (bzw. `seo.content_briefs.reconcile.POST`) holt für jeden
offenen Brief die erwartete Seite (`published_url` sonst `target_url`) per leichtem
HTTP-Fetch — **kein DataForSeo-Cost** — und liest den Marker. Stimmt die UUID:

1. Brief → Status `published`, `published_url` + `published_at` gesetzt,
2. die Seite wird als eigene, getrackte `seo_url` registriert (`is_own=true`),
3. das Ranking-/OnPage-Tracking läuft ab jetzt auf der Live-Seite.

Weil der Abgleich **marker-basiert** ist, übersteht er Slug-/URL-Änderungen und
braucht keine aktive Rückmeldung von Flynk. Empfehlung: `seo:reconcile-briefs`
täglich schedulen.

## Offene Erweiterungen

- Keyword-Re-Attribution: beim Publish die Cluster-Keywords von der Sammel-URL
  (z. B. nodera.health) auf die neue Unterseite umhängen.
- Org-Link: die neue `seo_url` automatisch an denselben Org-Knoten wie die
  Ziel-Domain hängen.
- URL-Discovery: Marker auch bei geänderter URL über einen Sitemap-/Site-Crawl
  wiederfinden (heute wird `target_url`/`published_url` geprüft).

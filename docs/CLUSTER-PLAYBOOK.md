# Cluster-Playbook — So arbeiten wir mit Keyword-Clustern

> Dies ist kein Feature-Handbuch, sondern **unser Vorgehen**. Das SEO-Modul ist
> die Manifestation dieser Methode. Wer hier etwas ändert, ändert unseren Standard.

---

## 1. Grundprinzip

Ein **Cluster ist ein Arbeitsgegenstand, kein Report.** Er gehört immer zu genau
**einem Kunden-Knoten** im Org-Baum (Broichs Themen ≠ Culinarias Themen).

Wir fahren **Top-down, gespeist aus Bottom-up**:

- **A — Entdecken (Bottom-up):** Der SERP-Overlap-Algorithmus schlägt aus dem
  Keyword-Bestand Themen-Gruppen vor. Das ist **Input**, kein Ergebnis.
- **B — Entscheiden & Arbeiten (Top-down):** Wir wählen aus, welches Thema wir
  **besitzen** wollen, geben ihm einen Namen und eine **Pillar-URL**, und
  arbeiten die Durchdringung hoch.

**A überschreibt niemals kuratierte Cluster.** A liefert nur Vorschläge:
neue Kandidaten und Waisen-Keywords (Keywords, die in keinen aktiven Cluster
passen = Themenlücke).

---

## 2. Die drei Takte

| Takt | Was passiert | Kadenz | Kosten |
|------|--------------|--------|--------|
| **Entdecken (A)** | Kandidaten + Waisen vorschlagen | periodisch, z. B. wöchentlich nach dem Keyword-Refresh — oder on-demand | DataForSEO-Budget |
| **Messen** | Durchdringung/Health/Trajektorie aller aktiven Cluster snapshotten | **täglich** (`seo:snapshot-clusters`) | keine |
| **Arbeiten** | Pro Cluster: Lücke → Content → Messung | ereignisgetrieben (Mensch/Agent) | — |

Discovery **schlägt vor**, Menschen **entscheiden**, Messung **läuft von allein**.

---

## 3. Der Cluster-Lebenszyklus

```
 candidate ──promote──► active ──durchdrungen──► monitored
                          │  ▲                        │
                    stillstand │                  rückfall
                          ▼  │                        ▼
                        stalled ──entscheiden──► (re-scope→active | archived)
```

| Status | Bedeutung | Eintritts-Gate |
|--------|-----------|----------------|
| `candidate` | Von A vorgeschlagen, noch nicht besessen | Discovery schreibt nur diesen Status |
| `active` | Kuratiert, wird bearbeitet | **Name + Pillar-URL + Ziel-Keywords gesetzt** (Definition of Done fürs Aktivieren) |
| `monitored` | Gut durchdrungen, Pflege-Modus | Durchdringung ≥ 70 **und** Top-10-Anteil ≥ 50 %, gehalten über ≥ 2 Snapshots |
| `stalled` | Aktiver Cluster ohne Fortschritt | ≥ 8 Wochen mit ΔDurchdringung < +5 Punkte |
| `archived` | Aufgegeben / abgeschlossen-und-tot | Bewusste Entscheidung aus `stalled` |

**Rückfall:** Fällt ein `monitored`-Cluster unter Durchdringung 60 oder verliert
Top-10-Keywords, geht er automatisch zurück auf `active`.

---

## 4. Der Erfolgs-Quotient: Durchdringung (0–100)

Die eine Zahl, die zählt: **Wie viel des gewinnbaren Raums eines Themas besitzen wir?**

```
Durchdringung = 100 × ( 0.40 · Coverage
                      + 0.40 · Top10-Anteil
                      + 0.20 · Top3-Anteil )
```

- **Coverage** = Keywords des Clusters, für die wir überhaupt ranken / alle Ziel-Keywords
- **Top10-Anteil** = Keywords in Top 10 / alle Ziel-Keywords
- **Top3-Anteil** = Keywords in Top 3 / alle Ziel-Keywords

Gewichte und Schwellen sind Defaults und in `config/seo.php` justierbar.
(Ersetzt den bisherigen `health_score`; siehe Struktur-Schritt.)

Neben dem absoluten Wert zählt die **Trajektorie**: Durchdringung wird täglich
gesnapshottet, der Trend über 90 Tage ist das Steuerungssignal (steigt / flach / fällt).

---

## 5. Der Arbeits-Loop (5 Phasen)

```
1 ENTDECKEN   A schlägt Kandidaten + Waisen vor           [periodisch, Vorschlag]
2 ENTSCHEIDEN Kandidat → active: Name, Pillar-URL, Ziel-KWs [Mensch kuratiert]
3 PLANEN      Coverage-Lücken → Content-Briefs (Rolle: pillar/supporting)
4 PRODUZIEREN Brief → Content → an URL gemappt
5 MESSEN      täglich Durchdringung + Trajektorie → Review → zurück zu 3
```

**Definition of Done je Phase**
- **Entscheiden:** Cluster hat Name, genau **eine Pillar-URL**, Ziel-Keyword-Set. Sonst bleibt er `candidate`.
- **Planen:** Jede offene Coverage-Lücke hat entweder einen Content-Brief oder eine bewusste „ignorieren"-Notiz.
- **Produzieren:** Content ist veröffentlicht **und** einer SEO-URL zugeordnet.
- **Messen:** Cluster erscheint im täglichen Snapshot; Trajektorie ist sichtbar.

---

## 6. Hygiene-Regeln (Pflicht, nicht optional)

**6.1 Stagnierende Cluster.** Ein `active`-Cluster ohne Fortschritt (≥ 8 Wochen,
ΔDurchdringung < +5) wird `stalled` und **muss** entschieden werden:
**re-scope** (Ziel-Keywords/Pillar/Content neu) **oder archivieren**. Kein
stiller Dauer-Leerlauf.

**6.2 Nicht-rankende, clusterlose URLs.** Eine eigene URL, die
- in **keinem Top-20** rankt **und**
- **keinem aktiven Cluster** dient (weder Pillar noch Supporting) **und**
- älter als 60 Tage ist,

ist **Ballast** und wird zur Entscheidung vorgelegt: **entfernen** oder
**umarbeiten** (Content-Overhaul, Merge, Redirect). Jede gehaltene URL soll
entweder ranken oder einem Cluster dienen.

**6.3 Waisen-Keywords.** Keywords, die A keinem aktiven Cluster zuordnen kann,
sind Themenlücken-Signale: entweder neuen Kandidaten bilden oder bewusst verwerfen.

---

## 7. Fokus-Prinzip (WIP-Limit)

Leitbild: *„Schnittstellen eliminieren, auf Meilensteine fokussieren."*
Deshalb je Kunde nur eine **begrenzte Zahl `active`-Cluster gleichzeitig**
(Default: 3). Erst wenn einer `monitored` wird, wird Kapazität für einen neuen
`active` frei. Durchdringung schlägt Breite.

---

## 8. Verantwortlichkeiten (Takt-Eigner)

- **Entdecken:** automatisiert (Discovery-Job) → Mensch triagiert Kandidaten.
- **Entscheiden / Planen:** Mensch (bzw. Agent im Auftrag) — das ist die Kuration.
- **Produzieren:** Content-Erstellung, an SEO-URL gemappt.
- **Messen / Hygiene-Alerts:** automatisiert (Snapshots + Regel-Checks aus §6),
  legen Empfehlungen/Signale an.

---

## 9. Datenmodell-Verankerung (folgt im Struktur-Schritt)

Damit der Standard erzwungen statt nur beschrieben ist:

- `SeoKeywordCluster.status` — `candidate | active | monitored | stalled | archived`
- `SeoKeywordCluster.pillar_url_id` — die eine Ziel-URL (heute nur implizit über Brief-Rolle)
- Durchdringung ersetzt/erweitert `health_score`; Schwellen in `config/seo.php`
- Cluster hängt via `SeoOrganizationLinker` am Kunden-Knoten (Pflicht)
- Hygiene-Regeln (§6) laufen als geplante Checks und erzeugen Empfehlungen

---

*Blaupause: validiert an Broich (BHG.BROICHCATERING) — Discovery → 1–2 echte
Cluster mit Pillar → ein Loop-Durchlauf. Was dort trägt, ist der Standard.*

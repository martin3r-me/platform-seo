# Nordstern — das SEO-Zielbild

> **Kanonisch.** Dieses Dokument ist der Nordstern für Architektur *und* UI des SEO-Moduls.
> Es **revidiert** das Zwei-Haltungen-Modell aus `WIRKUNGSRAUM-CONCEPT.md` (Liste vs.
> Wirkungsraum): **„Liste" als eigenes Konzept entfällt.** Bei Widerspruch gilt dieses Dokument.

> **Namens-Mapping (UI ↔ Code):** Wirkungsraum = `SeoPortfolio`/`seo_portfolios`.
> URL = `SeoUrl`. Cluster = `SeoKeywordCluster`. Keyword = `SeoKeyword`.
> UI/Konzept deutsch, Code englisch — dasselbe Ding.

---

## Mission

Über **Jahre** in **vielen Gebieten** organische Reichweite **kontrollieren** — nicht je
Einzelseite, sondern als **Maschine + Landkarte**: klug ausgesteuert über einen Verbund
kontrollierter Properties, hin zur maximalen gemeinsamen Sichtbarkeit.

## Das Grundprinzip der Oberfläche

> **Eine Fläche = eine Frage = eine nächste Aktion.**
> Vereinfachen heißt **subtrahieren**, nicht umsortieren. Alles, was keine Entscheidung
> trägt, wird versteckt (progressive disclosure) oder gelöscht. Seiten waren voll, weil sie
> um *gesammelte Daten* gebaut waren statt um *die nächste Handlung*. Um die Handlung
> organisieren = gleichzeitig einfacher **und** Regelkreis schließen.

---

## Drei Konzepte, zwei Achsen, ein Treffpunkt

Das ganze Tool ist die **Heirat zweier Achsen**, die sich an *einem* Punkt treffen: dem **Pillar**.

- **Angebots-Achse — unsere Seiten:** `URL` → `Wirkungsraum`
- **Nachfrage-Achse — die Themen (Landkarte):** `Keyword` → `Cluster`
- **Treffpunkt:** ein **Cluster (Thema)** wird von *einer* **Pillar-URL (Seite)** besessen.

### 1. URL — die Basis (nur lesend)

- **Job:** konsolidiert *alles* über *eine* Seite und sammelt Daten. Wahrheit, kein Handlungsort.
- **Die eine echte Unterscheidung** lebt hier: **eigen** (wir beeinflussen den Inhalt) vs.
  **extern** (tun wir nicht). Das ist das **Arbeitspferd** — es entscheidet, ob „handeln"
  später **bauen/ändern** (eigen) oder **Empfehlung nach außen** (extern) heißt.
- **Bleibt an der URL:** die **Org-/Engagement-Knoten-Zuordnung** (Basis-Verdrahtung) und der
  **Rückbericht** an diese Knoten (die URL reportet ihre Daten nach oben).
- **Erlaubte Aktion:** höchstens **ein** Knopf „jetzt holen" (Daten sofort sammeln). Sonst nichts.
- **Zeigt abgeleitet:** die **Cluster, die sie bedient**, und ihren **Wirkungsgrad** (s. u.).

### 2. Wirkungsraum — der einzige Handlungsort

- **Job:** wo **gehandelt** wird. Verheiratet **URLs (Angebot) × Cluster (Nachfrage)** am Pillar:
  Themen sauber auf Seiten verteilen, Kannibalisierung vermeiden, gezielt verlinken.
- **Mitglieder:** **gemischt** — eigene *und* externe URLs. URL-Überlappung zwischen
  Wirkungsräumen ist **erlaubt und unbeschränkt** (eine URL ist nur eine Property).
- **Gesamtsicht immer.** Handeln passt sich der URL an: eigen → direkt, extern → Empfehlung.
- **„Liste" entfällt:** eine Liste war nur ein Wirkungsraum, den man gerade *nur betrachtet* —
  das ist eine **Haltung/ein Zustand**, kein eigener Typ. (Leichtgewichtige „gespeicherte
  Sicht/Filter" bleibt als reine UI-Bequemlichkeit erlaubt, nicht als gleichrangiges Konzept.)
- **Gated Werkbank:** der **Reifegrad** (Messen → Ordnen → Verteilen → Vertiefen → Konvertieren)
  entscheidet, welches *eine* Werkzeug gerade sichtbar ist. Nicht alles auf einmal.

### 3. Cluster — die Nachfrage-Achse (im Wirkungsraum verankert)

- **Job:** ein **Thema** = Bündel von Keywords. Die „Landkarte" der Nachfrage.
- **Verankerung:** `cluster.wirkungsraum_id` (Anker) + `cluster.pillar_url_id` (Besitzer-Seite).
  Ein Cluster → **ein** Wirkungsraum + **ein** Pillar.
- **Harte Regel — Keyword-Eindeutigkeit:** ein Keyword zahlt auf **genau ein** Cluster ein
  (`keyword.cluster_id`, einwertig). **Doppelvergabe ist verboten.**
- **Warum das der Kannibalisierungs-Wächter *ist*:** über sein Cluster löst sich ein Keyword
  **eindeutig** zu einem Wirkungsraum + Pillar auf. Beansprucht ein zweiter Wirkungsraum
  dasselbe Keyword, *kann* er es nicht still danebenlegen → der **Konflikt wird sichtbar**.
- **Nicht zu stur:** der Geo-/Modifier-Splitter trennt „catering düsseldorf" (Broich) von
  „catering wuppertal" (Culinaria) in **verschiedene Keywords** → verschiedene Cluster →
  verschiedene Besitzer. Nur der *exakt gleiche* Begriff ist umkämpft — und der soll einen
  Besitzer haben. Die Regel ist **präzise, nicht pauschal**.

---

## Wirkungsgrad — das URL-Echo

Aus der Komposition ergibt sich an der URL (read-only, abgeleitet):

> **Wirkungsgrad = eingelöste ÷ erreichbare Nachfrage** der Themen, die die URL bedient.

Über alle Cluster, in die die URL mit ihren Keywords einzahlt: wie viel des erreichbaren
Potenzials (Cluster-Volumen) sie **real** einholt (GSC-IST), volumen-gewichtet, 0–100 %.
Hoch = die Seite besitzt ihre Themen wirksam. Niedrig = berührt Themen, lässt Nachfrage
liegen → Kandidat fürs Handeln **im Wirkungsraum**. Es ist das URL-Echo des Cluster-GSC-IST/
Potenzial-Gaps.

---

## Metrik-Wahrheit

Die **echte Sichtbarkeit (GSC-IST)** ist Leitmetrik, nicht die DataForSeo-„Abdeckung".
„Abdeckung/Durchdringung 100 %" bei Positionen 48–57 ist eine Lüge (= „rankt irgendwo").
GSC-IST („X/Y Begriffe sichtbar", echte Position, Klicks, Lücke) führt.

---

## Was zu bauen ist, damit es „sauber" ist (offene Pflichten)

1. **Claim-Check am Schreib-Pfad.** Beim „Zimmer übernehmen"/Cluster-Erstellen prüfen, ob ein
   Keyword schon in einem anderen Cluster (bes. anderem Wirkungsraum) liegt → als bewusster,
   sichtbarer **Kannibalisierungs-Konflikt** anzeigen (übernehmen/umziehen oder liegen lassen),
   statt still umzuhängen.
2. **`cluster.wirkungsraum_id`** einführen (Anker), `pillar_url_id` bleibt.
3. **Wirkungsgrad** als abgeleitete URL-Kennzahl (aus Cluster-Zugehörigkeit × GSC-IST).
4. **Loop schließen:** „→ Brief erstellen" aus Signal **und** Cluster; Brief-Status sichtbar
   (briefed → in Arbeit → live). Heute: 24 Briefs, 0 geschifft.

---

## Der Regelkreis

**sehen → entscheiden → handeln → messen → sehen.** Die Maschine *sieht und plant* stark; der
letzte Meter (Brief → geschiffte Seite → gemessen) ist der Engpass. Jede Vereinfachung
organisiert eine Fläche um genau diesen Kreis. **Timebox:** eine Fläche nach der anderen,
jede einzeln ausgeliefert und einfacher — nicht das ganze Modul auf einmal.

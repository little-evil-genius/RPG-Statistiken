# RPG-Statistiken
Dieses Plugin erweitert ein Forum um ein umfassendes Statistiksystem. Neben den klassischen Statistiken rund um User:innen und das Inplay können auch individuelle Diagramme und Variablen erstellt werden – als Datenquellen dienen dabei Profilfelder, <a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbrieffelder</a> oder Benutzergruppen. Alle Statistikwerte sind global einsetzbar und lassen sich flexibel in allen Templates oder PHP-Dateien integrieren. Die Ausgaben können einzeln erfolgen oder zusätzlich auf einer mitgelieferten, zentralen Statistikseite gebündelt dargestellt werden.<br>
Für den Index stehen zwei neue Variablen zur Verfügung: eine vollständig anpassbare Anzeige der zuletzt angenommenen (gewobbten) Charaktere sowie eine kompakte Übersicht über die neuesten Themen und Beiträge im Forum – ebenfalls individuell gestaltbar.

## Funktionen im Überblick
### Diagramme und Variabeln im ACP
Neben den fest integrierten Standardstatistiken bietet das Plugin die Möglichkeit, individuelle Auswertungen direkt über das Admin-CP zu erstellen – ganz ohne Eingriffe in PHP-Dateien. Die Erstellung erfolgt bequem über ein Formular. Als Datenquellen können klassische MyBB-Profilfelder, Benutzergruppen oder - sofern installiert - Steckbrieffelder aus dem Plugin <a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP</a> von risuena verwendet werden. Wichtig ist, dass bei der Auswahl von Feldern nur solche berücksichtigt werden können, die über feste Auswahloptionen verfügen. Dazu zählen Auswahlwahlbox (Select), Auswahlbox mit mehreren Optionen (Select Mehrfachauswahl), Radiobuttons & Checkboxen.<br>
Sowohl die erstellten Variablen als auch Diagramme erhalten einen eindeutigen Identifikator und können anschließend global verwendet werden – etwa in Templates oder PHP-Dateien. Dadurch lassen sich alle individuelle Statistiken flexibel einbinden.<br>
<br>
<b>Variablen</b><br>
Variablen sind einfache Auszählfunktionen, die immer einen numerischen Wert (Zahl) zurückgeben. Sie eignen sich ideal, um zum Beispiel darzustellen, wie viele Accounts eine bestimmte Option in einem Profil- oder Steckbrieffeld ausgewählt haben oder wie viele Mitglieder sich aktuell in einer bestimmten Benutzergruppe befinden. Ein typisches Anwendungsbeispiel wäre die Ausgabe, wie viele User im Profilfeld "Lieblingsfarbe" die Option "Rot" gewählt.<br>
Jede Variable besteht aus einem eindeutigen Identifikator, über den die Variable im Forum eingebunden wird. Die Ausgabe erfolgt ganz einfach über den folgenden Platzhalter:<br><br>
```{$mybb->rpgstatistic['count_identifikator']}```<br><br>
Dabei wird ausschließlich ein Zahlenwert ausgegeben - Texte oder weitere Inhalte sind nicht enthalten!<br>
<br>
<b>Diagramme</b><br>
Diagramme dienen der grafischen Darstellung der Verteilung von Profil- oder Steckbriefoptionen bzw. Benutzergruppen. Zur Verfügung stehen drei Darstellungsformen: Balkendiagramme, Kreisdiagramme (wahlweise mit oder ohne Legende) sowie eine einfache Listenansicht mit Bezeichnung und Anzahl der Treffer. Sie eignen sich besonders zur Veranschaulichung von Verteilungen, beispielsweise bei Geschlechtsangaben oder Gruppenzugehörigkeiten.<br>
Jedes Diagramm kann mit einem individuellen Titel versehen werden, der als Überschrift ausgegeben wird. Für die visuelle Anpassung lassen sich die Farben der einzelnen Datenpunkte manuell definieren. Dabei sind sowohl klassische Farbwerte wie Hex- oder RGB-Codes als auch CSS Custom Properties (z. B. var(--chart_red)) möglich. Die Ausgabe erfolgt über den folgenden Platzhalter:<br><br>
```{$mybb->rpgstatistic['chart_identifikator']}```<br><br>
So können die Diagramme flexibel an beliebiger Stelle im Forum eingebunden werden – sei es im Index, in einem Template oder auf einer gesonderten Seite.

### Forengeburtstag
Das Plugin ermöglicht es, ein Datum für den sogenannten Forengeburtstag zu hinterlegen – das ist der Tag, an dem das Forum offiziell eröffnet wurde. Dabei ist wichtig, dass das Datum in den Einstellungen im Format <b>TT.MM.JJJJ</b> eingetragen wird, damit es korrekt verarbeitet werden kann.<br>
Das Plugin stellt zwei verschiedene Werte rund um den Geburtstag bereit. Zum einen gibt es einen reinen Zahlenwert, der angibt, wie viele Tage seit der Eröffnung bis zum aktuellen Tag vergangen sind. Zum anderen wird eine Textvariante ausgegeben, die die vergangene Zeit anschaulich in "X Jahre, X Monate und X Tage" darstellt.<br>
Wie alle Statistikwerte sind auch diese Daten vollständig global einsetzbar und können problemlos in jedem Template oder in PHP-Dateien verwendet werden. Die entsprechenden Variablen lauten:<br>
- Forengeburtstag: ```{$mybb->rpgstatistic['forumbirthday']}```
- Anzahl der Tage seit Eröffnung: ```{$mybb->rpgstatistic['forumbirthday_days']}```
- Ausführliche Textvariante: ```{$mybb->rpgstatistic['forumbirthday_fullDate']}```

### Inplay- und Account-Statistiken
Das Herzstück von RPG-Foren sind die Charaktere und die Beiträge im Inplay. Das Plugin bietet eine einfache und übersichtliche Ausgabe genau dieser Werte. In den Einstellungen können UIDs von Accounts angegeben werden, die bei den Statistiken nicht berücksichtigt werden sollen, beispielsweise Teamaccounts.<br>
<br>
<b>User:innen-Statistik</b><br>
Es werden die folgenden Werte ausgegeben, wie viele Charaktere insgesamt im Forum angemeldet sind, wie viele Spieler:innen diese Charaktere steuern und wie hoch die durchschnittliche Anzahl an Charakteren pro Spieler:in ist. Auch diese Variablen sind global einsetzbar und können in jedem Template oder jeder PHP-Datei verwendet werden. Die entsprechenden Variablen lauten:<br>
- Anzahl Spieler:innen: ```{$mybb->rpgstatistic['countPlayer']}```
- Anzahl Charaktere: ```{$mybb->rpgstatistic['countCharacter']}```
- Durchschnittliche Charakteranzahl: ```{$mybb->rpgstatistic['averageCharacter']}```
<br>
<b>Inplay-Statistik</b><br>
Für die Inplay-Statistiken werden die Anzahl der Inplayszenen, der Inplayposts sowie die Gesamtzahl der geschriebenen Zeichen und Wörter ermittelt. Zusätzlich werden jeweils die durchschnittlichen Werte für Zeichen und Wörter ausgegeben. Das Plugin unterstützt dabei vier verschiedene Inplay-Tracker-Systeme: den <a href="https://github.com/its-sparks-fly/Inplaytracker-2.0">Inplaytracker 2.0</a> und <a href="https://github.com/ItsSparksFly/mybb-inplaytracker">Inplaytracker 3.0</a> von sparks fly, den <a href="https://github.com/katjalennartz/scenetracker">Szenentracker von Risuena</a>, den <a href="https://github.com/little-evil-genius/Inplayszenen-Manager">Inplayszenen-Manager von mir (little.evil.genius)</a> sowie den <a href="https://github.com/Ales12/inplaytracker-2.0">Inplaytracker 2.0 von Ales</a>. Auch diese Werte sind global verfügbar und können in Templates oder PHP eingebunden werden. Die entsprechenden Variablen lauten:<br/><br>

- Anzahl Inplayszenen: ```{$mybb->rpgstatistic['inplayscenes']}```
- Anzahl Inplayposts: ```{$mybb->rpgstatistic['inplayposts']}```
- Gesamtanzahl aller Zeichen: ```{$mybb->rpgstatistic['allCharacters']}```
- Durchschnittliche Anzahl Zeichen: ```{$mybb->rpgstatistic['averageCharacters']}```
- Gesamtanzahl aller Wörter: ```{$mybb->rpgstatistic['allWords']}```
- Durchschnittliche Anzahl Wörter: ```{$mybb->rpgstatistic['averageWords']}```

### Top-Inplaypost Statistik
Auch wenn das Schreiben im Inplay kein Wettbewerb sein soll, bietet das Plugin die Möglichkeit, die aktivsten Spieler:innen und Charaktere anhand ihrer Inplaypost-Anzahl zu ermitteln. Dabei wird zwischen Gesamtaktivität, Monatsaktivität und Tagesaktivität unterschieden. Die Auswertung erfolgt getrennt nach Spieler:innen (alle Accounts zusammengezählt) und einzelnen Charakteren. So kann gezielt sichtbar gemacht werden, wer aktuell besonders aktiv ist - oder über längere Zeit hinweg konstant viel postet.<br>
Folgende Optionen stehen zur Verfügung und können jeweils einzeln in den Einstellungen aktiviert oder deaktiviert werden:
- Top-Spieler:in mit den meisten Inplayposts insgesamt
- Top-Spieler:in mit den meisten Inplayposts im aktuellen Monat
- Top-Spieler:in mit den meisten Inplayposts am aktuellen Tag
- Top-Charakter mit den meisten Inplayposts insgesamt
- Top-Charakter mit den meisten Inplayposts im aktuellen Monat
- Top-Charakter mit den meisten Inplayposts am aktuellen Tag
<br>
In allen Fällen wird der Accountname (bzw. Spitzname) zusammen mit der jeweiligen Anzahl an Inplayposts ausgegeben. Auch diese Werte sind vollständig global einsetzbar und lassen sich flexibel in Templates oder PHP-Dateien einbinden. Die zugehörigen Variablen lauten:<br><br>

- Top-Spieler:in (insgesamt): ```{$mybb->rpgstatistic['topUser']}```
- Top-Spieler:in (Monat): ```{$mybb->rpgstatistic['topUserMonth']}```
- Top-Spieler:in (Tag): ```{$mybb->rpgstatistic['topUserDay']}```
- Top-Charakter (gesamt): ```{$mybb->rpgstatistic['topCharacter']}```
- Top-Charakter (Monat): ```{$mybb->rpgstatistic['topCharacterMonth']}```
- Top-Charakter (Tag): ```{$mybb->rpgstatistic['topCharacterDay']}```
<br>
<b>Top X Ranking-Liste</b>
Wird die Statistikseite im Plugin aktiviert, kann zusätzlich eine erweiterte Rangliste angezeigt werden. Über die Einstellung "Top-Inplaypost Ranking" lässt sich festlegen, wie viele Platzierungen dargestellt werden sollen - etwa die Top 3, Top 5 oder Top 10. Diese erweiterte Liste steht ausschließlich auf der mitgelieferten Statistikseite zur Verfügung und ermöglicht eine übersichtliche Darstellung der aktivsten Spieler:innen bzw. Charaktere.

### zuletzt gewobbte Charaktere
Das Plugin bietet die Möglichkeit, eine frei definierbare Anzahl an zuletzt angenommenen bzw. "gewobbten" Charakteren auf dem Index anzuzeigen - ganz einfach über die Variable ```{$rpgstatistic_wob}```.<br>
Voraussetzung für diese Funktion ist, dass es in der Datenbanktabelle ```users``` eine Spalte gibt, in der das WoB-Datum (also das Datum der Annahme) gespeichert ist. Gängige Spaltennamen sind zum Beispiel:
- ```wob_date``` (<a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP - risuena</a>)
- ```wobSince``` (<a href="https://github.com/aheartforspinach/Whitelist">Whitelist 2.0 - aheartforspinach</a>)
- ```wobdate``` (<a href="https://github.com/Ales12/applicationoverview">Bewerberübersicht - Ales</a>)
Der Spaltenname lässt sich flexibel in den Plugin-Einstellungen hinterlegen.<br>
<br>
Die Ausgabe erfolgt standardmäßig einfach gehalten mit dem Accountnamen und dem klassischen Avatar des Charakters. Sie kann jedoch vollständig angepasst werden: Sämtliche Daten aus den Datenbanktabelle ```users``` , Profilfelder sowie aus den Plugins Steckbrieffelder und <a href="https://github.com/little-evil-genius/Upload-System" target="_blank">Uploadelemente</a> stehen zur Verfügung. Die Ausgabe einzelner Felder erfolgt über ```{$character['xx']}```, wobei xx je nach Quelle ersetzt werden muss:
- ```users```-Tabelle: Name der jeweiligen Spalte
- Profilfelder: fidX (wobei X die FID-Nummer ist)
- Steckbrieffelder: Identifikator des Feldes
- Uploadelemente: Identifikator des Elements
Der Avatar der zuletzt gewobbten Charaktere kann über die Variable ```{$avatarUrl}``` eingebunden werden - hierbei handelt es sich um die reine URL. Wenn der klassische Avatar - beim Uploadsystem passiert dies automatisch - genutzt wird, besteht zusätzlich die Möglichkeit, diesen vor Gästen auszublenden, auch wenn Gäste grundsätzlich Zugriff auf die Anzeige der zuletzt angenommenen Charaktere haben. In solchen Fällen kann automatisch eine Standardgrafik als Platzhalter angezeigt werden.<br>
Für die Darstellung des Charakternamens gibt es mehrere Möglichkeiten:
- Nur Gruppenfarbe: ```{$characternameFormatted}```
- Nur als Profil-Link: ```{$characternameLink}```
- Gruppenfarbe + Link kombiniert: ```{$characternameFormattedLink}```
- Getrennt als Vor- und Nachname: ```{$characternameFirst} & {$characternameLast}``` (Profilverlinkung über die UID mit ```{$uid}``` möglich)
<br>
Optional lässt sich die Anzeige der zuletzt gewobbten Charaktere auch zwischen den Foren anzeigen. Hierfür wird in den Einstellungen ein Foren bzw. Kategorien ausgewählt. Die Ausgabe erfolgt dann über die Variable ```{$forum['rpgstatistic_wob']}```, die in das Template forumbit_depth1_cat oder forumbit_depth2_forum eingefügt werden muss.<br>
<b>Wichtig:</b> Diese Anzeige ist ausschließlich für den Indexbereich vorgesehen und nicht global einsetzbar.

### Neuste Themen/Beiträge

### Statistikseite

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Datenbank-Änderungen
hinzugefügte Tabelle:
- rpgstatistic_charts
- rpgstatistic_variables

# Neue Sprachdateien
- deutsch_du/admin/rpgstatistic.lang.php
- deutsch_du/rpgstatistic.lang.php

# Einstellungen

# Neue Template-Gruppe innerhalb der Design-Templates
- RPG-Statistiken Templates

# Neue Templates (nicht global!)
- rpgstatistic_chart_bar
- rpgstatistic_chart_pie
- rpgstatistic_chart_word
- rpgstatistic_chart_word_bit
- rpgstatistic_overviewtable
- rpgstatistic_overviewtable_bit
- rpgstatistic_overviewtable_topics
- rpgstatistic_page
- rpgstatistic_page_charts
- rpgstatistic_page_charts_bit
- rpgstatistic_page_forumbirthday
- rpgstatistic_page_top
- rpgstatistic_page_top_range
- rpgstatistic_page_top_range_bit
- rpgstatistic_page_top_range_user
- rpgstatistic_page_top_single
- rpgstatistic_page_top_single_bit
- rpgstatistic_wob
- rpgstatistic_wob_bit

# Neue Variablen
- headerinclude: ```<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>```
- index_boardstats: ```{$rpgstatistic_overviewtable}``` & ```{$rpgstatistic_wob}```
- forumbit_depth1_cat/forumbit_depth2_forum: ```{$forum['rpgstatistic_wob']}```

# Neues CSS - rpgstatistic.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default.<br>
Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
```css
.rpgstatistic_chart_headline {
        margin-bottom: 5px;
        color: #333;
        font-size: small;
        font-weight: bold;
        text-transform: uppercase;
        text-align: center;
        width: 100%;
        }

        .rpgstatistic_chart {
        height: 150px;
        width: 100%;
        }

        .rpgstatistic_chart canvas {
        width: 100% !important;
        height: 100% !important;
        }

        .rpgstatistic_chart_word_bit {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        }

        .rpgstatistic_chart_word_count {
        justify-content: center;
        align-items: center;
        display: flex;
        flex-flow: column;
        }

        .rpgstatistic_chart_word_name {
        color: #293340;
        font-weight: bold;
        text-transform: uppercase;
        }

        .rpgstatistic_wob {
        background: #fff;
        width: 100%;
        margin: auto auto;
        border: 1px solid #ccc;
        padding: 1px;
        -moz-border-radius: 7px;
        -webkit-border-radius: 7px;
        border-radius: 7px;
        }

        .rpgstatistic_wob-headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #ffffff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        -moz-border-radius-topleft: 6px;
        -moz-border-radius-topright: 6px;
        -webkit-border-top-left-radius: 6px;
        -webkit-border-top-right-radius: 6px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        }

        .rpgstatistic_wobcharas {
        background: #f5f5f5;
        border: 1px solid;
        border-color: #fff #ddd #ddd #fff;
        padding: 10px 0;
        -moz-border-radius-bottomleft: 6px;
        -moz-border-radius-bottomright: 6px;
        -webkit-border-bottom-left-radius: 6px;
        -webkit-border-bottom-right-radius: 6px;
        border-bottom-left-radius: 6px;
        border-bottom-right-radius: 6px;
        display: flex;
        flex-wrap: nowrap;
        justify-content: center;
        }

        .rpgstatistic_wobcharas_character {
        width: 30%;
        text-align: center;
        }

        .rpgstatistic_wobcharas_username {
        font-size: 16px;
        font-weight: bold;
        }

        .rpgstatistic_wobcharas_avatar img {
        padding: 5px;
        border: 1px solid #ddd;
        background: #fff;
        }

        .rpgstatistic_overviewtable {
        background: #fff;
        width: 100%;
        margin: auto auto;
        border: 1px solid #ccc;
        padding: 1px;
        -moz-border-radius: 7px;
        -webkit-border-radius: 7px;
        border-radius: 7px;
        }

        .rpgstatistic_overviewtable_headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #fff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        -moz-border-radius-topleft: 6px;
        -moz-border-radius-topright: 6px;
        -webkit-border-top-left-radius: 6px;
        -webkit-border-top-right-radius: 6px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        }

        .rpgstatistic_overviewtable_content {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        background: #f5f5f5;
        }

        .rpgstatistic_overviewtable_bit {
        width: 100%;
        }

        .rpgstatistic_overviewtable_bit-headline {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 6px;
        }

        .rpgstatistic_overviewtable_bit-content {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        padding: 5px 3px;
        }

        .rpgstatistic_overviewtable_bit-item.user {
        text-align: end;
        }

        .rpgstatistic_page_subline {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 6px;
        font-size: 12px;
        font-weight: bold;
        }

        .rpgstatistic_page_statistic {
        display: flex;
        flex-flow: wrap;
        margin: 10px 0;
        justify-content: space-around;
        }

        .rpgstatistic_page-stat {
        padding: 10px 5px;
        width: 30%;
        }

        .rpgstatistic_page-stat_answer {
        text-align: center;
        color: #333;
        font-size: small;
        font-weight: bold;
        text-transform: uppercase;
        }

        .rpgstatistic_page-stat_question {
        text-align: center;
        }

        .rpgstatistic_page_range {
        width: 33%;
        }

        .rpgstatistic_page_range-headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #fff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        -moz-border-radius-topleft: 6px;
        -moz-border-radius-topright: 6px;
        -webkit-border-top-left-radius: 6px;
        -webkit-border-top-right-radius: 6px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        }

        .rpgstatistic_page_range-content {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        background: #f5f5f5;
        justify-content: space-around;
        font-size: 11px;
        padding: 5px 3px;
        }

        .rpgstatistic_page_top_range-user {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        background: #f5f5f5;
        justify-content: space-around;
        font-size: 11px;
        padding: 5px 3px;
        }

        .rpgstatistic_page_range-item {
        width: 50%;
        text-align: center;
        }

        .rpgstatistic_page_top_range_user-item {
        width: 50%;
        text-align: center;
        }

        .rpgstatistic_page_chart {
        width: 50%;
```

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Diagramme und Variabeln für die RPG-Statistiken haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für die Felder befinden sich im Tab 'RPG Erweiterungen'.

# Links
<b>ACP</b><br>
index.php?module=rpgstuff-rpgstatistic<br>
<br>
<b>Statistikseite</b><br>
usercp.php?action=rpgstatistic

# Demo
### ACP

### Statistikseite

### zuletzt angenommene Charaktere

### neue Themen/Beiträge-Tabelle

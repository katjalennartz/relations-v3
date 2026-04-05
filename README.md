# Relations v3 (MyBB Plugin)

Das ist ein MyBB-Plugin zur Verwaltung von Beziehungen zwischen Charakteren.
User können Beziehungen im UCP verwalten. Diese werden im Profil angezeigt. 


---

## Vorraussetzungen
* Laras RPG Modul
* plugins/risuena_updates/risuena_updatefile.php **muss** mit hochgeladen werden (im upload ordner hinterlegt)

---

## Features

* Beziehungen zwischen Usern erstellen und verwalten
* Bestätigungs-System (Anfragen an andere Nutzer) Automatische Bestätigung kann vom User eingestellt werden.
* Anzeige der Beziehungen im Profil
* NPC-Unterstützung (inkl. Name, Bild, Daten)
* Freitext-Kommentare zu Beziehungen
* Sortierung und Kategorisierung
* Dynamische Kategorien & Subkategorien (Defaults sind über das ACP verwaltbar)
* Eigene Übersichts- und Anzeigeoptionen
* Optional: HTML / MyCode Unterstützung
* Wird ein Charakter gelöscht, werden eingetragene Beziehgungen auf NPCs umgetragen
* NPCs können als gesucht markiert werden - User und Gäste können direkt eine PN bei Interesse schicken. (inkl. spamschutz bei Gästen)
* Gesuchte Charas können im Profil extra aufgelistet werden

---

## Kategorien-System

Die Kategorien sind vollständig dynamisch:

* Hauptkategorien (z. B. Familie, Freunde, etc.) sind von Usern frei erstellbar
* Subkategorien (z. B. Mutter, bester Freund, etc.) sind von Usern frei erstellbar
* Default Kategorien werden über das ACP erstellt.
* Beliebig erweiterbar

Fallback-System vorhanden, falls keine Default Kategorien erstellt wurden.

---

## Installation

1. Dateien aus dem `upload`-Ordner ins Forum hochladen
2. Im ACP installieren
3. Einstellungen im ACP vornehmen Settings und im RPG Erweiterungen Reiter, default Kategorien erstellen.

---

## Datenbank

Das Plugin nutzt mehrere Tabellen

* `relas_entries` → Beziehungen
* `relas_categories` → Hauptkategorien
* `relas_categories_default` → Default Hauptkategorien
* `relas_subcategories` → Subkategorien
* `relas_subcategories_default` → Default Subkategorien

---

## Einstellungen

Im ACP kannst du folgendes konfigurieren

* Relations Bestätigung (Müssen Anfragen anderer Charaktere bestätigt werden?) 
* Relations Alerts (Sollen Alerts überhaupt benutzt werden?)
* Relations Löschung (Soll der User per Alert informiert werden, wenn eine Relation zu ihm gelöscht wird?) 
* Gäste und Avatare (Dürfen Gäste Avatare sehen?) 
* Breite der Avtare (Wie breit sollen die Avatare im Profil dargestellt werden?)
* NPCs (Dürfen NPCs eingetragen werden?)
* NPCs Bilder (Dürfen für die NPCs Bilder eingetragen werden?)
* HTML (Darf in der Beschreibung der Relation html verwendet werden?)
* MyCode (Darf in der Beschreibung der Relation MyCode verwendet werden?)
* Relations nach WOB? (Dürfen Relations erst nach dem WOB gestellt werden?)
* Bewerbergruppe (Wie ist die Gruppe für die User die noch nicht angenommen sind?)
* Ingamezeit (Von welchem Ingamemonat und Jahr ausgehend soll das Alter berechnet werden? z.B. 2026-01 für Januar 2026)
* Wie wird der Gebursjahr des Charakters angegeben?
* Jobliste (Ihr verwendet die jobliste von risuena (https://github.com/katjalennartz/jobliste) dann könnt ihr den Job von Charakteren in den Relations automatisch mit ausgeben lassen.)  

---

## Templates & Styling

Das Styling ist noch sehr rudimentär und muss angepasst werden.

---

## NPC-Funktion

Neben echten Usern können auch NPCs angelegt werden:

* eigener Name
* optional Bild
* Geburts-/Sterbejahr
* Beschreibung

---

[1]: https://github.com/katjalennartz?utm_source=chatgpt.com "Katja katjalennartz - GitHub"

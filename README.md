
# HTL Typegrid (Drupal Modul)

**Beschreibung:**  
Ein Block-Modul für Drupal, das Inhalte in einem **Karten-Grid** (z. B. 3×2) darstellt.  
Ideal für Typ-/Bundle-basierte Übersichten und visuelle Darstellungen.

- **Core:** Drupal 11  
- **Lizenz:** MIT  
- **Paketname:** `ftmahringer/htl_typegrid_module`  
- **Versionierung:** über Git-Tags (`vX.Y.Z`)

---

## 🔧 Voraussetzungen

- Drupal 11.x  
- PHP gemäß Drupal-Anforderungen  
- Composer  
- (Empfohlen) Drush  

---

## 🚀 Installation

> ⚠️ **Wichtig:**  
> Nur das Hinzufügen eines `"type": "vcs"`-Eintrags in der `composer.json` reicht **nicht aus**!  
> Das Repository muss explizit per Composer-Befehl registriert werden.

### 1️⃣ VCS-Repository hinzufügen
```bash
composer config repositories.ftm vcs https://github.com/FTMahringer/htl_typegrid_module
```

### 2️⃣ Modul installieren
```bash
# Neueste stabile 1.x-Version:
composer require ftmahringer/htl_typegrid_module:^1

# Oder exakt:
# composer require ftmahringer/htl_typegrid_module:v1.2.0
```

> Das Modul wird automatisch nach  
> `web/modules/contrib/htl_typegrid` installiert (Standard bei drupal/recommended-project).

### 3️⃣ Modul aktivieren
```bash
drush en htl_typegrid -y
drush cr
```
Oder über das UI:  
**Erweiterungen → „HTL Typegrid“ → Aktivieren → Speichern → Cache leeren**

---

## 🧱 Block platzieren

1. **Struktur → Block-Layout**  
2. Beim aktiven Theme auf **„Block platzieren“** klicken  
3. **„HTL Grid“** auswählen  
4. Region auswählen → Speichern  

> Der Block kann (je nach Konfiguration) Felder, Anzahl und Spalten anpassen.

---

## ⚙️ Konfiguration (Überblick)

- **Inhaltstyp:** frei wählbar  
- **Attribute / Felder:** definierbar  
- **Links oder Teaser:** optional

---

## 🔄 Updates

Das Modul verwendet **SemVer-Tags** (`vX.Y.Z`).

```bash
composer update ftmahringer/htl_typegrid_module
drush cr
```

Falls Composer das Paket nicht findet:
```bash
composer clear-cache
composer show -a ftmahringer/htl_typegrid_module
```
Prüfe, ob du das VCS-Repo hinzugefügt hast (siehe oben).

---

## 🧪 Schnelltest (ohne Tag)

> Nur für Tests — **nicht** produktiv verwenden:
```bash
composer require ftmahringer/htl_typegrid_module:dev-main --prefer-source
```
Danach wieder auf eine stabile Version (`^1`) umsteigen.

---

## ❗ Troubleshooting

| Problem | Lösung |
|----------|--------|
| `Package could not be found` | Repository mit `composer config repositories.ftm vcs https://github.com/FTMahringer/htl_typegrid_module` hinzufügen |
| `Invalid version string ^1.x` | `^1.x` ist ungültig – nutze `^1`, `1.*` oder `v1.2.0` |
| `minimum-stability: stable` | Nur stabile Tags verwenden (`v1.2.0`, nicht `-dev`) |
| Falscher Installationspfad | Stelle sicher, dass `extra.installer-paths` in `composer.json` gesetzt ist |

---

## 🧰 Entwicklung & Release

Releases werden automatisch per GitHub Actions erstellt.  
Die Commit-Message oder der manuelle Start bestimmen den Bump:

| Flag | Beispiel | Ergebnis |
|------|-----------|-----------|
| `-upgrade` | `refactor!: API change -upgrade` | **v(X+1).0.0** |
| `-release` | `feat: grid update -release` | **vX.(Y+1).0** |
| `-patch` | `fix: null check -patch` | **vX.Y.(Z+1)** |

Die Version in `htl_typegrid.info.yml` wird automatisch mit dem Tag synchronisiert.

---

## 📄 Lizenz

MIT – siehe `LICENSE`.

---

## 🙋 Support

Issues und Vorschläge bitte über GitHub:  
➡️ [FTMahringer/htl_typegrid_module](https://github.com/FTMahringer/htl_typegrid_module)

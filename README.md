# Pizza-Planer

Entstanden aus der Not, gehackt an einem Nachmittag, geliefert in 20 Minuten.
Der #kitinfo Pizza-Planer erlaubt das ersetzen vieler in Diskussion und muehsamer
Planung verbrachter Stunden durch ein simples Webpanel - Ideal fuer Parties, Meetings,
oder Abendplanung.

Jeder Teilnehmer kann sich mit minimalen Daten anmelden, Pizzas (oder andere Items) anlegen,
Interesse bekunden und sich so zu Essensgruppen zusammenfinden.

# Setup

Benoetigt

* HTTPd mit PHP5 (bspw lighttpd mit php5-cgi)
* den SQLite-PDO-Treiber fuer PHP5

Debian-Pakete lighttpd, php5-cgi, php5-sqlite

Repository in einen vom HTTPd servierten Ordner klonen,
Datenbank (`server/pizza.empty.db3`) irgendwo sicheres hinkopieren.

Der Ordner der die Datenbank enthaelt sowie die Datei selbst muessen
vom HTTPd aus lese- und schreibbar sein.

Das Adminpasswort steht in der `system`-Tabelle und kann dort geaendert werden (mit
einem beliebigen Admin-Tool fuer SQLite Datenbanken, bspw `sqlite3`).

# Konfiguration

Installieren, dann in `server/database.php` Zeila 17 den Pfad zur Datenbank angeben.

Sollte funktionieren.

Guten Hunger!

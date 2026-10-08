# SiteKit Example

## Übersicht Sitekit Extensions


### ot-sitekit-base-distribution

https://packagist.org/packages/oliverthiele/ot-sitekit-base-distribution

Läd alle Composer-Pakete die im Sitekit zwingend vorhanden sein müssen, ähnlich,
wie es auch bei der typo3/cms-base-distribution gemacht wird.

- helhum/typo3-console
- oliverthiele/ot-febuild
- typo3/cms-base-distribution
- typo3/cms-scheduler
- typo3/cms-linkvalidator
- vlucas/phpdotenv

Dev:

- roave/security-advisories
- typo3/coding-standards
- typo3/cms-lowlevel

und weitere …


### ot-sitekit-base

Minimale Extension, in der alles definiert wird, was von mehreren
Sitekit-Extensions importiert wird. Die wichtigsten Elemente werden hier
eingebunden:
 - Gemeinsame ViewHelper
 - Die wichtigsten Content-Elemente wie Text, Text & Bild, Card


### ot-sitekit-full

Basis-Extension, in der alle Erweiterungen geladen werden, die für das Sitekit
geprüft bzw. optimiert worden sind.
Also ideal zum Testen, ob alles zusammen funktioniert.


### ot-sitekit-text

Content Element für die Ausgabe von Text.

Vorteile gegenüber dem TYPO3 Standard-Element:
- Buttons können leicht hinzugefügt werden
- Mehrspaltiger Text ohne Container-Extension


### ot-sitekit-text-image

Content Element für die Ausgabe von Text mit Bildern:

Vorteile gegenüber dem TYPO3 Standard-Element:
- Responsive: Img-Tag mit srcset
- Reihenfolge von Überschrift, Bild und Text ist im Mobil/Desktop optimiert

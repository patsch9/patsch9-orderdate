# Versionierung

Releases verwenden `YYYY.MM.N`; Vorabversionen `YYYY.MM.N-alphaN` oder `YYYY.MM.N-betaN`. Erste Entwicklungsversion: `2026.11.1-beta1`.

Der Tag ist die autoritative Paketversion. Die Build-Routine setzt Plugin-Header, `P9OD_VERSION`, `Stable tag` und Block-Metadaten exakt auf diesen Tag. Ein Release darf nur von einem Commit aus `main` gebaut werden. Beta-/Alpha-Tags werden als GitHub-Prerelease veröffentlicht.

Die gleiche, feste Ordnerstruktur `patsch9-orderdate/` ermöglicht WordPress, ein hochgeladenes Release als Update zu erkennen. GitHub-Quellcodearchive können branchabhängige Ordnernamen enthalten und sind dafür nicht vorgesehen.

Es gibt kein WordPress.org-Deployment und keine automatische Update-Abfrage bei GitHub. Installiert wird das Release-ZIP über WordPress.

Aktuelle Entwicklungsversion: `2026.11.1-beta3` mit Abholzeitfenstern.


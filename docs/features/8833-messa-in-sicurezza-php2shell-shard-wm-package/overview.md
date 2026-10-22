> Ticket: oc:8833

# Messa in sicurezza PHP2Shell — parte WordPress (wp-forestas)

## Cosa cambia

1. **Pin della versione PHP nel Dockerfile**: `docker/configs/php/Dockerfile` passa da
   `FROM php:8.3-apache` (tag mobile) a `FROM php:8.3.36-apache` (versione che chiude le
   CVE-2026-109618/109619). Con il tag mobile l'aggiornamento di PHP era un evento invisibile
   legato al momento del rebuild; col pin ogni bump è un commit tracciato — stesso schema del
   repo forestas.
2. **Rebuild e riavvio** del container (su UAT: `scripts/wordpress-up.sh` dell'host, che in
   questo stesso lavoro riceve `--pull` — vedi overview nel repo forestas): la verifica è
   `php -v` **dentro il container** (≥ 8.3.36), mai l'esito della build.

Il container WordPress rientra inoltre nella raccolta evidenze e nella verifica di
compromissione descritte nell'overview del repo forestas (è esposto al pubblico con lo stesso
motore vulnerabile).

## Perché

Stesse CVE del ticket oc:8833 (vedi overview nel repo forestas): la falla è nel motore PHP e il
container WordPress ne monta uno vulnerabile. Il tag mobile rendeva inoltre impossibile
dimostrare da git quando la patch è entrata: il pin risolve entrambe le cose.

## Requisiti

- [ ] `FROM php:8.3.36-apache` nel Dockerfile
- [ ] Container WordPress ricostruito; `php -v` nel container conferma ≥ 8.3.36
- [ ] Il sito e un upload media da WordPress funzionano dopo il rebuild

## Rischi

- Il rebuild con base in cache e senza `--pull` è un no-op silenzioso: mitigato dal pin (la
  base 8.3.36 non è in cache, va scaricata per forza) e dal `--pull` nello script; criterio di
  verifica è `php -v` nel container.

## Out of scope

- `LimitRequestBody` nei vhost UAT: sull'Apache 2.4.41 dell'host non si applica alle richieste
  proxate (verificato) — il tema host è tracciato in oc:8835. I limiti PHP del container
  (`wordpress.ini`: 64M) restano quelli e bastano.
- Il ciclo di azzeramento quotidiano di WordPress (oc:8711/oc:8717): non c'entra con questo
  lavoro.

## Moduli toccati

| File | Modifica |
|---|---|
| `docker/configs/php/Dockerfile` | `FROM php:8.3-apache` → `FROM php:8.3.36-apache` |

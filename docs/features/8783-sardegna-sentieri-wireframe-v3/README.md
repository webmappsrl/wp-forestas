> Ticket: oc:8783

# Wireframe v3 del nuovo sardegnasentieri.it

Il wireframe è un prototipo HTML delle pagine principali del nuovo sito, rivisto con Forestas fra
settembre e ottobre 2026 (commenti sul wireframe del 12-13/10, call del 16/10). Questa cartella ne
raccoglie le decisioni e le indicazioni tecniche per costruire le pagine in WordPress.

## Come si usa

- **Il riferimento resta il sito attuale.** Regola del `CLAUDE.md`: la UI è quella di
  sardegnasentieri.it (Drupal), salvo modifiche concordate con il cliente. Il wireframe raccoglie
  proprio le modifiche concordate: dove dice qualcosa di diverso dal sito attuale, vale il
  wireframe; dove tace, vale il sito attuale.
- **Valgono le misure reali, non quelle del prototipo.** Le tavole sono larghe circa 1130px ma
  rappresentano una finestra di 1440px: molte misure sono in proporzione (`cqw`). In WordPress si
  usano i valori del sito attuale e dei breakpoint del child (1024/1025px, 600px).
- **I contenuti sono reali, salvo dove è dichiarato.** Dal 19/10/2026 home, ricerca, schede di
  sentiero, itinerario, cammino e punto di interesse, avvisi e sezioni usano dati veri del sito
  attuale e di Geohub (app 32); le note dicono cosa è un esempio (voti e recensioni, percorribilità,
  che oggi non esistono). L'itinerario a tappe (tavola 6) non è ancora stato riallineato. Un dato
  entra nella pagina solo se esiste o se è stato deciso di produrlo: vedi
  [decisioni.md](decisioni.md), «Stato dei dati».
- **Le note numerate delle tavole** spiegano ogni scelta con la fonte e il criterio WCAG. Sono la
  versione breve di quanto scritto qui.

## Link

- Copia di lavoro: https://claude.ai/artifact/8waB9S4U9qvHehNyUiCFFi — versione 181 al 19/10/2026,
  la più aggiornata.
- Originale condiviso con Forestas: https://claude.ai/artifact/4T3ZRSP1LCrbCuJdNuEGXQ — accesso
  riservato; riceve le modifiche della copia di lavoro a blocchi.

## Tavole

| Tavola | Contenuto |
|---|---|
| Fondamenti | colori, caratteri, pulsanti del sito attuale; stato di percorribilità a semaforo |
| 1 Home | avvisi, slider, ricerca, stato della rete, aree territoriali, mappa interattiva, catasto, catalogo, punti di interesse, ultimi contenuti |
| 2 Ricerca unica | filtri, indirizzo della ricerca, elenco e mappa, selezione e scarico di più sentieri |
| 3 Scheda sentiero | avviso, testata con fascia scura, azioni, fascia di percorribilità, tre indicatori, dati catastali, mappa, moduli, testata compatta |
| 4 Scheda itinerario | stessa struttura della scheda sentiero, dati dell'itinerario con «Itinerario in progetto» |
| 5 Scheda cammino | la scheda di una traccia del cammino, come il sentiero, più il blocco «Il cammino, in sintesi» |
| 6 Itinerario a tappe | elenco delle tappe, tratti fuori catasto, due indicatori derivati |
| 7 Punto di interesse | tipologia in testata, mappa della rete, percorsi vicini calcolati sulle tracce |
| 8 Da sapere e News | sezioni a schede con indirizzo proprio, card unica |
| 9 Cammini, aree, rifugi | ruolo di ciascun tipo di contenuto, un solo elenco di sentieri |
| 10 Elenco degli avvisi | pagina `/avvisi` con gli avvisi attivi |

In fondo al wireframe la sezione «Che cosa manca per chiudere» elenca il deciso del 16/10, ciò che si
attende da Forestas e le decisioni ancora aperte.

## File di questa cartella

- [decisioni.md](decisioni.md) — le decisioni sul wireframe e il loro stato al 19/10/2026, tavola per
  tavola, con la fonte; le decisioni aperte; lo stato dei dati (cosa esiste, cosa va calcolato, cosa
  deve fornire Forestas, cosa è deciso ma da sviluppare).
- [implementazione-wordpress.md](implementazione-wordpress.md) — come costruire le pagine: componenti,
  misure, colori, contrasti, breakpoint, comportamenti e regole di accessibilità (WCAG 2.1 AA, con i
  rapporti di contrasto).

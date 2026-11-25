/*
 * Source della ricerca del child (inc/search.php), registrate nel plugin wp-package solo se c'è: il child non chiama
 * mai il plugin direttamente.
 * - «Contenuti del sito» (oc:8952): news, eventi, «da sapere» e pagine dalla rotta REST forestas/v1/search, che dà le
 *   card pronte e i conteggi per tipo.
 * - «Enti» (oc:8990): gli enti dello shard dalla rotta forestas/v1/entes-search, che chiama l'API degli enti dal server
 *   con il token del sito (mai nel browser). Filtri «Tipo di ente» e «Ruolo sui sentieri» (oc:9000): le voci, già contate
 *   e ordinate, le dà la rotta.
 */
(function () {
  'use strict';

  var api = window.wmWpSearch;
  var config = window.forestasSearch;
  if (!api || typeof api.registerSource !== 'function' || !config) {
    return;
  }

  api.registerSource('content', {
    search: function (query, signal) {
      var url = new URL(config.endpoint, window.location.href);
      var chosen = (query.filters && query.filters.content) || [];
      if (query.rawText) {
        url.searchParams.set('q', query.rawText);
      }
      if (chosen.length) {
        url.searchParams.set('type', chosen.join(','));
      }
      url.searchParams.set('page', String(query.page));
      url.searchParams.set('per_page', String(query.perPage));
      url.searchParams.set('lang', query.lang);
      return fetch(url.toString(), { signal: signal, headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('HTTP ' + response.status);
          }
          return response.json();
        })
        .then(function (data) {
          var byType = data.by_type || {};
          // I conteggi per tipo non dipendono dal tipo scelto: sono già quelli «senza il proprio filtro»
          var values = Object.keys(byType)
            .filter(function (key) { return byType[key] > 0 || chosen.indexOf(key) !== -1; })
            .map(function (key) {
              return { id: key, label: (config.types && config.types[key]) || key, count: byType[key] || 0, selected: chosen.indexOf(key) !== -1 };
            })
            .sort(function (a, b) { return b.count - a.count; });
          return {
            total: data.total || 0,
            items: data.items || [],
            facets: [{ key: 'content', label: '', disabled: false, values: values }]
          };
        });
    }
  });

  if (config.entesEndpoint) {
    // Chiavi in inglese, uguali in tutte le lingue (oc:9018)
    var ENTES_FACETS = ['entity-type', 'role'];
    api.registerSource('entes', {
      search: function (query, signal) {
        var url = new URL(config.entesEndpoint, window.location.href);
        var filters = query.filters || {};
        if (query.rawText) {
          url.searchParams.set('q', query.rawText);
        }
        ENTES_FACETS.forEach(function (key) {
          if (filters[key] && filters[key].length) {
            url.searchParams.set(key, filters[key].join(','));
          }
        });
        url.searchParams.set('page', String(query.page));
        url.searchParams.set('per_page', String(query.perPage));
        url.searchParams.set('lang', query.lang);
        return fetch(url.toString(), { signal: signal, headers: { Accept: 'application/json' } })
          .then(function (response) {
            if (!response.ok) {
              // Ricerca degli enti giù (503): la promessa fallisce con il messaggio della rotta, e il plugin mostra
              // l'errore nel tab «Enti» («—» al posto del numero) mentre gli altri tab vanno avanti
              return response.json().then(
                function (data) { throw new Error((data && data.message) || 'HTTP ' + response.status); },
                function () { throw new Error('HTTP ' + response.status); }
              );
            }
            return response.json();
          })
          .then(function (data) {
            var facets = data.facets || {};
            return {
              total: data.total || 0,
              items: data.items || [],
              facets: ENTES_FACETS.map(function (key) {
                return { key: key, label: '', disabled: false, values: facets[key] || [] };
              })
            };
          });
      }
    });
  }
})();

/*
 * Source «Contenuti del sito» della ricerca (oc:8952): news, eventi, «da sapere» e pagine dalla rotta REST
 * forestas/v1/search del child (inc/search.php), che dà le card pronte e i conteggi per tipo. Si registra nel
 * plugin wp-package solo se c'è: il child non chiama mai il plugin direttamente.
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
      var chosen = (query.filters && query.filters.contenuto) || [];
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
            facets: [{ key: 'contenuto', label: '', disabled: false, values: values }]
          };
        });
    }
  });
})();

"""Aggiunge le icone di Forestas (contorni.py) al set IcoMoon dello shard e scrive il file da caricare su /icons-upload.

Uso:
    curl -o set-attuale.json https://<shard>/wmfe/<nome-shard>/json/icons.json
    python3 contorni.py nuove.json
    python3 aggiungi-al-set.py set-attuale.json nuove.json icons.json

Il file caricato sostituisce il set dello shard: per questo si parte da quello attuale dello stesso ambiente (dev e UAT
possono avere icone diverse). Le icone nuove si aggiungono e basta: tutte quelle che c'erano restano, con lo stesso
disegno e lo stesso codice; un'icona con lo stesso nome di una nuova (solo se rilanciato su un set che le ha già) viene
aggiornata. Lo script controlla, prima di scrivere, che nessuna icona del set di partenza manchi o sia cambiata, e se
no si ferma senza scrivere il file.
"""
import json
import sys

base, nuove, uscita = sys.argv[1], sys.argv[2], sys.argv[3]
dati = json.load(open(base))
icone = json.load(open(nuove))
nomi = [s.get('name') for s in dati['selection']]
codici = {ic['defaultCode'] for ic in dati['icons'] if 'defaultCode' in ic}
for s in dati['selection']:
    codici.update([s['code']] if 'code' in s else [])
    codici.update(s.get('codes', []))
codice = max(codici) + 1
ordine = max(s.get('order', 0) for s in dati['selection']) + 1
for nome, paths in icone.items():
    if nome in nomi:
        i = nomi.index(nome)
        dati['icons'][i]['paths'] = paths
        print('sostituita', nome)
        continue
    dati['icons'].append({'paths': paths, 'tags': [nome], 'defaultCode': codice, 'grid': 0})
    dati['selection'].append({'order': ordine, 'name': nome, 'prevSize': 32, 'code': codice})
    print('aggiunta', nome, hex(codice))
    codice += 1
    ordine += 1
# Controllo: ogni icona del set di partenza c'è ancora, con disegno e codice di prima (tranne quelle nuove aggiornate)
partenza = json.load(open(base))
dopo = {s.get('name'): (dati['icons'][i], s) for i, s in enumerate(dati['selection'])}
for i, s in enumerate(partenza['selection']):
    nome = s.get('name')
    if nome in icone:
        continue
    if nome not in dopo or dopo[nome][0] != partenza['icons'][i] or dopo[nome][1] != s:
        sys.exit('ERRORE: l\'icona ' + str(nome) + ' del set di partenza manca o è cambiata, file non scritto')
assert len(dati['icons']) == len(dati['selection'])
json.dump(dati, open(uscita, 'w'), separators=(',', ':'))
print('tutte le', len(partenza['icons']), 'icone del set di partenza ci sono ancora, invariate')
print(len(dati['icons']), 'icone in', uscita)

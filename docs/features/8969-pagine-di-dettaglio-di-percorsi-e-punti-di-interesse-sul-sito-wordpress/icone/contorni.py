"""Converte le icone a tratto del dettaglio (viewBox 24, stroke 1.8, estremi e giunzioni tondi) in forme piene per un
set IcoMoon (griglia 1024): ogni segmento diventa un rettangolo, ogni vertice un cerchio, tutti con lo stesso verso;
con la regola nonzero si uniscono. I cerchi a tratto diventano anelli (cerchio esterno e interno con verso opposto)."""
import math, re, json, sys

S = 1024 / 24
W = 1.8 / 2  # mezzo spessore, in unità 24

def tok(d):
    return re.findall(r'[A-Za-z]|-?(?:\d+\.?\d*|\.\d+)(?:e-?\d+)?', d)

def arc_points(x1, y1, rx, ry, phi, fa, fs, x2, y2, n=24):
    # da SVG endpoint a centro (rx = ry, phi = 0 nelle nostre icone)
    dx, dy = (x1 - x2) / 2, (y1 - y2) / 2
    lam = (dx * dx) / (rx * rx) + (dy * dy) / (ry * ry)
    if lam > 1:
        rx *= math.sqrt(lam); ry *= math.sqrt(lam)
    num = rx*rx*ry*ry - rx*rx*dy*dy - ry*ry*dx*dx
    den = rx*rx*dy*dy + ry*ry*dx*dx
    co = math.sqrt(max(0, num / den)) * (-1 if fa == fs else 1)
    cxp, cyp = co * rx * dy / ry, -co * ry * dx / rx
    cx, cy = cxp + (x1 + x2) / 2, cyp + (y1 + y2) / 2
    a1 = math.atan2((dy - cyp) / ry, (dx - cxp) / rx)
    a2 = math.atan2((-dy - cyp) / ry, (-dx - cxp) / rx)
    da = a2 - a1
    if fs and da < 0: da += 2 * math.pi
    if not fs and da > 0: da -= 2 * math.pi
    k = max(4, int(abs(da) / (2 * math.pi) * n * 2))
    return [(cx + rx * math.cos(a1 + da * i / k), cy + ry * math.sin(a1 + da * i / k)) for i in range(1, k + 1)]

def cubic(p0, p1, p2, p3, k=16):
    out = []
    for i in range(1, k + 1):
        t = i / k; u = 1 - t
        out.append((u**3*p0[0] + 3*u*u*t*p1[0] + 3*u*t*t*p2[0] + t**3*p3[0], u**3*p0[1] + 3*u*u*t*p1[1] + 3*u*t*t*p2[1] + t**3*p3[1]))
    return out

def polylines(d):
    t = tok(d); i = 0; cmd = None; cur = (0, 0); start = (0, 0); lines = []; pl = None; last_c2 = None
    def num():
        nonlocal i; v = float(t[i]); i += 1; return v
    while i < len(t):
        if re.match(r'[A-Za-z]', t[i]):
            cmd = t[i]; i += 1
            if cmd in 'Zz':
                if pl: pl.append(start); pl_closed = True
                cur = start; continue
        rel = cmd.islower(); C = cmd.upper()
        if C == 'M':
            x, y = num(), num()
            if rel: x, y = cur[0] + x, cur[1] + y
            cur = start = (x, y); pl = [cur]; lines.append(pl); cmd = 'l' if rel else 'L'; last_c2 = None; continue
        if C == 'L':
            x, y = num(), num()
            if rel: x, y = cur[0] + x, cur[1] + y
            cur = (x, y); pl.append(cur); last_c2 = None
        elif C == 'H':
            x = num(); x = cur[0] + x if rel else x; cur = (x, cur[1]); pl.append(cur); last_c2 = None
        elif C == 'V':
            y = num(); y = cur[1] + y if rel else y; cur = (cur[0], y); pl.append(cur); last_c2 = None
        elif C in 'CS':
            if C == 'C':
                c1 = (num(), num())
                if rel: c1 = (cur[0] + c1[0], cur[1] + c1[1])
            else:
                c1 = (2*cur[0] - last_c2[0], 2*cur[1] - last_c2[1]) if last_c2 else cur
            c2 = (num(), num()); e = (num(), num())
            if rel: c2 = (cur[0] + c2[0], cur[1] + c2[1]); e = (cur[0] + e[0], cur[1] + e[1])
            pl.extend(cubic(cur, c1, c2, e)); last_c2 = c2; cur = e
        elif C == 'A':
            rx, ry, phi, fa, fs, x, y = num(), num(), num(), num(), num(), num(), num()
            if rel: x, y = cur[0] + x, cur[1] + y
            pl.extend(arc_points(cur[0], cur[1], rx, ry, phi, int(fa), int(fs), x, y)); cur = (x, y); last_c2 = None
    return lines

def area(poly):
    return sum(poly[k][0]*poly[(k+1) % len(poly)][1] - poly[(k+1) % len(poly)][0]*poly[k][1] for k in range(len(poly))) / 2

def orient(poly, sign):
    return poly if (area(poly) > 0) == (sign > 0) else poly[::-1]

def circle(cx, cy, r, n=20):
    return [(cx + r*math.cos(2*math.pi*k/n), cy + r*math.sin(2*math.pi*k/n)) for k in range(n)]

def outline(prims):
    shapes = []
    for kind, data in prims:
        if kind == 'ring':
            cx, cy, r = data
            shapes.append(orient(circle(cx, cy, r + W, 40), 1)); shapes.append(orient(circle(cx, cy, r - W, 40), -1))
            continue
        for pl in polylines(data):
            pts = [p for k, p in enumerate(pl) if k == 0 or math.dist(p, pl[k-1]) > 1e-6]
            for a, b in zip(pts, pts[1:]):
                L = math.dist(a, b); nx, ny = -(b[1]-a[1]) / L * W, (b[0]-a[0]) / L * W
                shapes.append(orient([(a[0]+nx, a[1]+ny), (b[0]+nx, b[1]+ny), (b[0]-nx, b[1]-ny), (a[0]-nx, a[1]-ny)], 1))
            # Cerchi alle estremità e agli angoli netti (oltre 20°), ottagoni lungo le curve
            chiusa = math.dist(pts[0], pts[-1]) < 1e-6
            for k, p in enumerate(pts):
                if 0 < k < len(pts) - 1 or chiusa:
                    a0 = pts[k-1] if k > 0 else pts[-2]
                    b0 = pts[k+1] if k < len(pts) - 1 else pts[1]
                    u = (p[0]-a0[0], p[1]-a0[1]); v = (b0[0]-p[0], b0[1]-p[1])
                    cosang = (u[0]*v[0] + u[1]*v[1]) / (math.hypot(*u) * math.hypot(*v))
                    if cosang > math.cos(math.radians(20)):
                        # giunzione liscia: basta un ottagono per chiudere lo spicchio sul lato esterno
                        shapes.append(orient(circle(p[0], p[1], W, 8), 1))
                        continue
                shapes.append(orient(circle(p[0], p[1], W, 16), 1))
    # Un solo path: con la regola nonzero le forme si uniscono e i fori degli anelli restano vuoti
    return [''.join('M' + 'L'.join('%s %s' % (round(x*S, 1), round(y*S, 1)) for x, y in sh) + 'Z' for sh in shapes)]

ICONE = {
    'txn-disability-motor': [('ring', (11, 3.8, 1.7)), ('path', 'M11 7v6h5l2.5 5.5M11 10h4.5'), ('path', 'M8 11.2a5.2 5.2 0 1 0 7.4 6.4')],
    'txn-disability-sensory': [('path', 'M3 14s3.5-5 9-5 9 5 9 5-3.5 5-9 5-9-5-9-5z'), ('ring', (12, 14, 2.4)), ('path', 'M12 3.5V6M6.6 5.2l1.4 2.1M17.4 5.2 16 7.3')],
    'txn-disability-cognitive': [('path', 'M12 5.5v13M12 5.5a3 3 0 0 0-5.6 1.2 3 3 0 0 0-1.9 5.1 3 3 0 0 0 1.4 4.5A3 3 0 0 0 12 18.5M12 5.5a3 3 0 0 1 5.6 1.2 3 3 0 0 1 1.9 5.1 3 3 0 0 1-1.4 4.5A3 3 0 0 1 12 18.5')],
}

if __name__ == '__main__':
    out = {name: outline(prims) for name, prims in ICONE.items()}
    json.dump(out, open(sys.argv[1], 'w'))
    for n, p in out.items():
        print(n, len(p), 'forme', sum(len(x) for x in p), 'caratteri')

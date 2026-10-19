"""
Generates the SAMPLE illustrations used by `php artisan doogsan:sample`.
They are credited "Sample illustration" and removed with --remove.
Run: python3 database/samples/make_illustrations.py
"""
import math, os, random
from PIL import Image, ImageDraw, ImageFilter

OUT = os.path.join(os.path.dirname(__file__), '..', '..', 'public', 'images', 'samples')
W, H = 1600, 1067

def lerp(a, b, t): return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))

def sky(img, top, bottom, horizon=1.0):
    d = ImageDraw.Draw(img)
    for y in range(H): d.line([(0, y), (W, y)], fill=lerp(top, bottom, min(1, y / (H * horizon))))

def sun(img, x, y, r, color, glow=2.4):
    layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); d = ImageDraw.Draw(layer)
    for i in range(30, 0, -1):
        rr = r * (1 + glow * i / 30); d.ellipse([x - rr, y - rr, x + rr, y + rr], fill=color + (int(110 * (1 - i / 30) ** 2),))
    d.ellipse([x - r, y - r, x + r, y + r], fill=color + (255,))
    img.alpha_composite(layer.filter(ImageFilter.GaussianBlur(6)))

def ridge(img, base, amp, color, seed, rough=6, alpha=255):
    rnd = random.Random(seed); phase = [rnd.random() * 10 for _ in range(rough)]
    pts = [(0, H)] + [(x, base + sum(math.sin(x / (120 + 90 * k) + phase[k]) * amp / (k + 1) for k in range(rough))) for x in range(0, W + 20, 20)] + [(W, H)]
    layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); ImageDraw.Draw(layer).polygon(pts, fill=color + (alpha,)); img.alpha_composite(layer)

def skyline(img, base, color, seed, lit=None, density=1.0):
    rnd = random.Random(seed); layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); d = ImageDraw.Draw(layer); x = -20
    while x < W:
        w = rnd.randint(50, 140); h = rnd.randint(90, 360) * density
        d.rectangle([x, base - h, x + w, H], fill=color + (255,))
        if rnd.random() < .15:
            cx = x + w / 2; d.ellipse([cx - 30, base - h - 34, cx + 30, base - h + 26], fill=color + (255,)); d.rectangle([cx - 3, base - h - 70, cx + 3, base - h - 30], fill=color + (255,))
        if lit:
            for wy in range(int(base - h + 18), base, 26):
                for wx in range(int(x + 10), int(x + w - 10), 20):
                    if rnd.random() < .33: d.rectangle([wx, wy, wx + 8, wy + 12], fill=lit + (rnd.randint(140, 255),))
        x += w + rnd.randint(0, 18)
    img.alpha_composite(layer)

def sea(img, top, ca, cb, seed):
    rnd = random.Random(seed); d = ImageDraw.Draw(img)
    for y in range(top, H): d.line([(0, y), (W, y)], fill=lerp(ca, cb, (y - top) / (H - top)))
    layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); ld = ImageDraw.Draw(layer)
    for _ in range(260):
        y = rnd.randint(top + 4, H); x = rnd.randint(-100, W); ln = rnd.randint(30, 220) * (0.4 + (y - top) / (H - top))
        ld.line([(x, y), (x + ln, y)], fill=(255, 255, 255, rnd.randint(18, 60)), width=2)
    img.alpha_composite(layer)

def cranes(img, base, color):
    d = ImageDraw.Draw(img)
    for cx, s in [(260, 1.0), (520, .8), (1250, 1.15)]:
        h = 360 * s
        d.rectangle([cx - 10 * s, base - h, cx + 10 * s, base], fill=color)
        d.polygon([(cx - 240 * s, base - h), (cx + 160 * s, base - h), (cx + 160 * s, base - h + 18 * s), (cx - 240 * s, base - h + 18 * s)], fill=color)
        d.line([(cx, base - h - 60 * s), (cx - 240 * s, base - h)], fill=color, width=int(5 * s)); d.line([(cx, base - h - 60 * s), (cx + 160 * s, base - h)], fill=color, width=int(5 * s))
    d.polygon([(700, base - 30), (1080, base - 30), (1050, base + 20), (730, base + 20)], fill=color)
    for i in range(6): d.rectangle([760 + i * 48, base - 78, 800 + i * 48, base - 32], fill=color)

def camels(img, base, color, seed):
    rnd = random.Random(seed); d = ImageDraw.Draw(img)
    for k in range(5):
        x = 180 + k * rnd.randint(170, 230); s = rnd.uniform(.75, 1.05); b = base + rnd.randint(-10, 20)
        d.ellipse([x, b - 120 * s, x + 150 * s, b - 60 * s], fill=color); d.ellipse([x + 40 * s, b - 150 * s, x + 110 * s, b - 90 * s], fill=color)
        for lx in (x + 15 * s, x + 35 * s, x + 105 * s, x + 128 * s): d.rectangle([lx, b - 70 * s, lx + 9 * s, b], fill=color)
        d.line([(x + 145 * s, b - 95 * s), (x + 190 * s, b - 165 * s)], fill=color, width=int(16 * s)); d.ellipse([x + 178 * s, b - 182 * s, x + 220 * s, b - 156 * s], fill=color)

def grid_nodes(img, color, seed):
    rnd = random.Random(seed); layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); d = ImageDraw.Draw(layer)
    pts = [(rnd.randint(0, W), rnd.randint(0, H)) for _ in range(90)]
    for i, p in enumerate(pts):
        for q in pts[i + 1:]:
            if math.dist(p, q) < 210: d.line([p, q], fill=color + (60,), width=2)
    for p in pts:
        r = rnd.randint(3, 9); d.ellipse([p[0] - r, p[1] - r, p[0] + r, p[1] + r], fill=color + (220,))
    img.alpha_composite(layer); img.alpha_composite(layer.filter(ImageFilter.GaussianBlur(14)))

def columns(img, base, color):
    d = ImageDraw.Draw(img); left, right = 260, 1340
    d.polygon([(left - 40, base - 520), (W / 2, base - 680), (right + 40, base - 520)], fill=color); d.rectangle([left - 40, base - 520, right + 40, base - 480], fill=color)
    for i in range(8):
        x = left + i * (right - left) / 7; d.rectangle([x - 26, base - 480, x + 26, base - 40], fill=color)
    d.rectangle([left - 70, base - 40, right + 70, base], fill=color); d.rectangle([left - 110, base, right + 110, base + 40], fill=color)

def mic(img, color, cx=W / 2, cy=H / 2 - 40):
    d = ImageDraw.Draw(img)
    d.rounded_rectangle([cx - 110, cy - 260, cx + 110, cy + 80], radius=110, fill=color)
    for y in range(int(cy - 220), int(cy + 60), 22): d.line([(cx - 90, y), (cx + 90, y)], fill=lerp(color, (0, 0, 0), .35), width=6)
    d.arc([cx - 170, cy - 120, cx + 170, cy + 180], 0, 180, fill=color, width=22); d.rectangle([cx - 12, cy + 180, cx + 12, cy + 330], fill=color)
    d.rounded_rectangle([cx - 140, cy + 320, cx + 140, cy + 350], radius=12, fill=color)

def rain(img, seed, alpha=70):
    rnd = random.Random(seed); layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); d = ImageDraw.Draw(layer)
    for _ in range(1400):
        x, y = rnd.randint(-200, W), rnd.randint(-50, H); d.line([(x, y), (x + 14, y + 46)], fill=(220, 235, 255, rnd.randint(25, alpha)), width=2)
    img.alpha_composite(layer)

def finish(img, name):
    rgb = img.convert('RGB'); rgb = Image.blend(rgb, Image.effect_noise((W, H), 22).convert('RGB'), .045)
    vign = Image.new('L', (W, H), 0); ImageDraw.Draw(vign).ellipse([-W * .25, -H * .3, W * 1.25, H * 1.3], fill=255)
    rgb = Image.composite(rgb, Image.new('RGB', (W, H), (8, 12, 10)), vign.filter(ImageFilter.GaussianBlur(160)))
    os.makedirs(OUT, exist_ok=True); rgb.save(os.path.join(OUT, f'{name}.jpg'), quality=84, optimize=True, progressive=True)
    for w in (480, 960): rgb.resize((w, int(H * w / W)), Image.LANCZOS).save(os.path.join(OUT, f'{name}-{w}.webp'), quality=80)

def port(i): sky(i, (24, 46, 78), (247, 160, 92), .62); sun(i, 1080, 600, 70, (255, 214, 140)); sea(i, 680, (40, 70, 92), (8, 24, 36), 1); cranes(i, 690, (14, 20, 26))
def city_dawn(i): sky(i, (18, 30, 56), (236, 170, 120), .8); sun(i, 420, 650, 60, (255, 225, 170)); skyline(i, 860, (52, 62, 84), 5, density=.8); skyline(i, 940, (20, 26, 38), 7, lit=(255, 210, 120))
def city_night(i): sky(i, (6, 10, 28), (30, 52, 90), .9); skyline(i, 900, (16, 22, 40), 11, lit=(255, 200, 110), density=1.2); skyline(i, 990, (8, 12, 22), 13, lit=(255, 230, 160))
def dunes(i): sky(i, (120, 170, 210), (250, 220, 170), .7); sun(i, 1200, 320, 80, (255, 245, 210), 1.6); ridge(i, 640, 60, (214, 164, 108), 2); ridge(i, 760, 70, (190, 132, 80), 3); ridge(i, 900, 60, (150, 96, 56), 4)
def herders(i): sky(i, (60, 84, 120), (245, 176, 100), .75); sun(i, 820, 640, 90, (255, 210, 130)); ridge(i, 700, 40, (110, 70, 50), 6); ridge(i, 790, 26, (40, 26, 20), 7); camels(i, 820, (22, 14, 10), 3)
def parliament(i): sky(i, (230, 236, 238), (190, 205, 210), 1); columns(i, 900, (36, 58, 54)); ridge(i, 990, 6, (28, 44, 40), 9)
def tech(i): sky(i, (3, 22, 26), (6, 50, 44), 1); grid_nodes(i, (165, 234, 79), 4)
def health(i):
    sky(i, (220, 240, 238), (160, 210, 205), 1); d = ImageDraw.Draw(i); pts = []
    for x in range(0, W, 8):
        k = x % 400; y = 540 - (220 if 160 < k < 180 else 0) + (160 if 180 <= k < 200 else 0) - (60 if 200 <= k < 215 else 0); pts.append((x, y))
    d.line(pts, fill=(10, 110, 100), width=10)
    for cx in (300, 1300): d.rectangle([cx - 30, 760, cx + 30, 940], fill=(255, 255, 255)); d.rectangle([cx - 90, 820, cx + 90, 880], fill=(255, 255, 255))
def education(i):
    sky(i, (250, 236, 210), (232, 196, 150), 1); rnd = random.Random(8); d = ImageDraw.Draw(i); x = 240
    pal = [(4, 50, 42), (165, 234, 79), (190, 70, 50), (40, 90, 140), (230, 180, 60)]
    while x < W - 240:
        w = rnd.randint(46, 92); h = rnd.randint(320, 520); c = rnd.choice(pal); d.rectangle([x, 880 - h, x + w, 880], fill=c); x += w + 4
    d.rectangle([160, 880, W - 160, 910], fill=(60, 40, 30))
def green_hills(i): sky(i, (150, 200, 220), (230, 240, 230), .7); ridge(i, 560, 70, (110, 160, 110), 10); ridge(i, 700, 80, (60, 120, 70), 11); ridge(i, 860, 60, (24, 78, 44), 12); rain(i, 3, 40)
def floods(i): sky(i, (70, 84, 96), (150, 160, 166), .7); skyline(i, 760, (52, 58, 60), 21, density=.4); sea(i, 760, (110, 100, 80), (60, 54, 44), 2); rain(i, 4, 80)
def stadium(i):
    sky(i, (4, 10, 24), (20, 40, 70), .7); layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); d = ImageDraw.Draw(layer)
    for lx in (140, 1460):
        d.rectangle([lx - 6, 120, lx + 6, 700], fill=(30, 34, 40, 255)); d.rectangle([lx - 70, 90, lx + 70, 150], fill=(255, 246, 210, 255))
    d.ellipse([-300, 640, W + 300, H + 520], fill=(28, 120, 70, 255)); d.ellipse([W / 2 - 160, 820, W / 2 + 160, 960], outline=(255, 255, 255, 120), width=5); d.line([(W / 2, 640), (W / 2, H)], fill=(255, 255, 255, 110), width=5)
    i.alpha_composite(layer)
def market(i):
    sky(i, (250, 214, 160), (240, 150, 90), 1); d = ImageDraw.Draw(i); rnd = random.Random(31)
    for k, x in enumerate(range(140, W - 140, 150)):
        h = rnd.randint(180, 620); d.rectangle([x, 920 - h, x + 96, 920], fill=(4, 50, 42) if k % 3 else (165, 234, 79))
    d.line([(140, 700), (520, 560), (900, 610), (1300, 360), (1460, 300)], fill=(255, 255, 255), width=9)
def studio(i):
    sky(i, (6, 14, 18), (14, 40, 36), 1); layer = Image.new('RGBA', (W, H), (0, 0, 0, 0)); d = ImageDraw.Draw(layer)
    for k in range(7):
        x = 140 + k * 220; d.polygon([(x, 0), (x + 40, 0), (x + 260, H), (x - 180, H)], fill=(165, 234, 79, 10))
    d.rounded_rectangle([420, 560, 1180, 760], radius=20, fill=(10, 30, 28, 255)); d.rounded_rectangle([560, 180, 1040, 470], radius=12, outline=(165, 234, 79, 200), width=6)
    i.alpha_composite(layer); m = Image.new('RGBA', (W, H), (0, 0, 0, 0)); mic(m, (190, 196, 192)); i.alpha_composite(m.resize((W // 3, H // 3)), (W // 2 - W // 6, 380))
def press(i): sky(i, (40, 46, 52), (80, 92, 96), 1); mic(i, (210, 214, 212))
def globe(i):
    sky(i, (4, 20, 30), (8, 46, 60), 1); d = ImageDraw.Draw(i); cx, cy, r = W / 2, H / 2, 380
    d.ellipse([cx - r, cy - r, cx + r, cy + r], outline=(165, 234, 79), width=6)
    for k in range(1, 6): rx = r * k / 6; d.ellipse([cx - rx, cy - r, cx + rx, cy + r], outline=(165, 234, 79), width=3)
    for k in range(-4, 5):
        y = cy + r * k / 5; half = math.sqrt(max(0, r * r - (y - cy) ** 2)); d.line([(cx - half, y), (cx + half, y)], fill=(165, 234, 79), width=3)
def road(i):
    sky(i, (140, 190, 230), (240, 220, 190), .6); ridge(i, 600, 30, (200, 160, 110), 14); d = ImageDraw.Draw(i)
    d.polygon([(W / 2 - 40, 620), (W / 2 + 40, 620), (W - 100, H), (100, H)], fill=(48, 50, 54))
    for k in range(10):
        t = k / 10; y = 640 + (H - 640) * t ** 1.4; w = 6 + 30 * t; d.rectangle([W / 2 - w / 2, y, W / 2 + w / 2, y + 12 + 40 * t], fill=(250, 210, 60))

SCENES = {'port-dusk': port, 'city-dawn': city_dawn, 'city-night': city_night, 'dunes': dunes, 'herders': herders, 'parliament': parliament,
          'tech-network': tech, 'health': health, 'education': education, 'green-hills': green_hills, 'floods': floods, 'stadium': stadium,
          'market': market, 'studio': studio, 'press': press, 'globe': globe, 'road': road}

if __name__ == '__main__':
    for n, f in SCENES.items():
        img = Image.new('RGBA', (W, H), (0, 0, 0, 255)); f(img); finish(img, n); print('made', n)

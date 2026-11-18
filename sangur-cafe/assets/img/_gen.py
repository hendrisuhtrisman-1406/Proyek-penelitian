import os, random, string
random.seed(42)
img_dir = r"C:\Users\spons\Dola\chats\2026-11-16\new-chat\sangur-cafe\assets\img"
menus = [
    ("nasi-goreng","Nasi Goreng","#8b5a2b"),
    ("mie-goreng","Mie Goreng","#a0522d"),
    ("chicken-wings","Chicken Wings","#b8651f"),
    ("beef-burger","Beef Burger","#6d4c41"),
    ("kentang-goreng","Kentang Goreng","#c98a5b"),
    ("roti-bakar","Roti Bakar","#795548"),
    ("kopi-hitam","Kopi Hitam","#3e2723"),
    ("cappuccino","Cappuccino","#5d4037"),
    ("teh-tarik","Teh Tarik","#a1887f"),
    ("es-coklat","Es Coklat","#4e342e"),
    ("jus-alpukat","Jus Alpukat","#8d6e63"),
    ("lemon-tea","Lemon Tea","#c9a227"),
]
for slug, nama, warna in menus:
    inisial = "".join([w[0] for w in nama.split()[:2]]).upper()
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0%" stop-color="{warna}"/><stop offset="100%" stop-color="#f5ebe0"/>
  </linearGradient></defs>
  <rect width="400" height="300" fill="url(#g)"/>
  <circle cx="200" cy="120" r="55" fill="rgba(255,255,255,0.25)"/>
  <text x="200" y="138" font-family="Arial" font-size="44" font-weight="bold" fill="#fff" text-anchor="middle">{inisial}</text>
  <text x="200" y="230" font-family="Arial" font-size="22" font-weight="bold" fill="#3e2723" text-anchor="middle">{nama}</text>
  <text x="200" y="260" font-family="Arial" font-size="13" fill="#5d4037" text-anchor="middle">Sangur Cafe</text>
</svg>'''
    with open(os.path.join(img_dir, slug + ".svg"), "w", encoding="utf-8") as f:
        f.write(svg)
for i in range(1,7):
    tok = "".join(random.choices(string.hexdigits.lower(), k=32))
    print("TABLE%d|%s" % (i, tok))
print("SVGs generated:", len(menus))

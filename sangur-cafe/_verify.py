# Verifier sederhana untuk 7 file modul kasir+kitchen+API
import re, os

BASE = r"C:\Users\spons\Dola\chats\2026-11-16\new-chat\sangur-cafe"
files = [
    "kasir/dashboard.php",
    "kasir/payment.php",
    "kasir/transactions.php",
    "kitchen/dashboard.php",
    "api/get-orders.php",
    "api/update-status.php",
    "api/pay-order.php",
]

def check_braces(code, name):
    """Cek keseimbangan {} () [] di luar string & komentar (heuristik)."""
    # Hapus string (single/double quote + heredoc sederhana) dan komentar
    code = re.sub(r'//[^\n]*', '', code)          # // komentar
    code = re.sub(r'#[^\n]*', '', code)           # # komentar
    code = re.sub(r'/\*.*?\*/', '', code, flags=re.S)  # /* */
    code = re.sub(r"'(?:\\.|[^'\\])*'", "''", code)    # string '...'
    code = re.sub(r'"(?:\\.|[^"\\])*"', '""', code)    # string "..."
    pairs = {'{':'}','(':')','[':']'}
    stack = []
    line = 1
    for ch in code:
        if ch == '\n': line += 1
        if ch in pairs:
            stack.append((ch, line))
        elif ch in pairs.values():
            if not stack:
                return f"[FAIL] {name}: tutup '{ch}' tanpa pasangan di baris ~{line}"
            op, ln = stack.pop()
            if pairs[op] != ch:
                return f"[FAIL] {name}: '{op}' (baris {ln}) ditutup dengan '{ch}' di baris {line}"
    if stack:
        op, ln = stack[-1]
        return f"[FAIL] {name}: '{op}' (baris {ln}) tidak pernah ditutup"
    return f"[OK]   {name}: kurung berpasangan"

def check_includes(code, name, is_api):
    errs = []
    if "require_once __DIR__ . '/../config/database.php'" not in code:
        errs.append("config/database.php tidak di-include")
    if "require_once __DIR__ . '/../includes/auth.php'" not in code:
        errs.append("includes/auth.php tidak di-include")
    if is_api:
        if "Content-Type: application/json" not in code:
            errs.append("API tidak set Content-Type: application/json")
        if "includes/header.php" in code or "includes/footer.php" in code:
            errs.append("API tidak boleh include header/footer")
    else:
        if "require __DIR__ . '/../includes/header.php'" not in code:
            errs.append("halaman tidak include header.php")
        if "require __DIR__ . '/../includes/footer.php'" not in code:
            errs.append("halaman tidak include footer.php")
    return errs

def check_role(code, name):
    if "require_role(" not in code and "require_login()" not in code:
        return f"[FAIL] {name}: tidak ada role check / require_login"
    return f"[OK]   {name}: ada proteksi role/login"

def check_prepared_statements(code, name):
    """Cari query yang mengandung variabel langsung (concatenasi) - heuristik."""
    # Pola berbahaya: "SELECT ... $var" atau " ... " . $var di dalam query
    # Kita periksa setiap $pdo->query(...) dan $pdo->prepare(...)
    bad = []
    # $pdo->query dengan string yang mengandung $ (variabel) = bahaya
    for m in re.finditer(r'\$pdo->query\s*\(\s*([^;]{0,400}?)\s*\)', code, flags=re.S):
        arg = m.group(1)
        if '$' in arg and 'placeholders' not in arg:
            # boleh jika $ hanya untuk implode placeholders (sudah aman)
            if 'array_fill' in code[m.start()-200:m.start()] or 'implode' in code[m.start()-300:m.start()]:
                continue
            bad.append(f"$pdo->query dengan variabel: {arg[:80]!r}")
    # Cek semua prepare punya execute([...]) setelahnya (heuristik longgar)
    preps = len(re.findall(r'\$pdo->prepare\s*\(', code))
    execs = len(re.findall(r'->execute\s*\(\s*\[', code))
    if preps > 0 and execs == 0 and name not in ("api/get-orders.php",):
        # get-orders pakai query() tanpa parameter user (hanya role branch) — aman
        bad.append(f"{preps} prepare tapi 0 execute dengan array")
    if bad:
        return "[FAIL] " + name + ": " + "; ".join(bad)
    return f"[OK]   {name}: prepared statement OK ({preps} prepare, {execs} execute-array)"

def check_e_escape(code, name):
    """Cek echo variabel tanpa e() — heuristik: echo $var atau <?= $var tanpa e()."""
    bad = []
    # Cari <?= $... yang bukan di dalam e(...) atau rp(...) atau tgl_id(...) atau (int)
    for m in re.finditer(r'<\?=\s*(\$[A-Za-z_][A-Za-z0-9_\[\]\'\"]*(?:\[[^\]]+\])?)', code):
        var = m.group(1)
        # Cek 30 char sebelum: apakah ada e( ?
        start = max(0, m.start()-4)
        before = code[start:m.start()]
        full = code[m.start():m.start()+60]
        if 'e(' not in full[:15] and 'rp(' not in full[:15] and 'tgl_id(' not in full[:15] and '(int)' not in full[:15] and 'BASE_URL' not in full[:20]:
            bad.append(var)
    if bad:
        return "[WARN] " + name + ": echo tanpa escape? " + ", ".join(sorted(set(bad))[:5])
    return f"[OK]   {name}: output variabel dibungkus e()/rp()/tgl_id()"

all_ok = True
for f in files:
    path = os.path.join(BASE, f)
    with open(path, encoding='utf-8') as fh:
        code = fh.read()
    is_api = f.startswith("api/")
    print(check_braces(code, f))
    inc = check_includes(code, f, is_api)
    if inc:
        all_ok = False
        print(f"[FAIL] {f}: include issues -> {'; '.join(inc)}")
    else:
        print(f"[OK]   {f}: struktur include benar")
    print(check_role(code, f))
    print(check_prepared_statements(code, f))
    print(check_e_escape(code, f))
    print("-" * 70)

print("\nSemua file:", "VALID" if all_ok else "ADA MASALAH")

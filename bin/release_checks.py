#!/usr/bin/env python3
"""Release checks for the webfiable-info plugin.

Standard library only. Runs in CI (GitHub runner, Python 3.12) and locally.
Never shipped: `bin/` is listed in `.distignore`.

Sub-commands
  coherence [--tag vX.Y.Z]            version, names, requirements and "Tested up to"
  zip <path> [--expect-version X.Y.Z] the package: required files present, nothing else
  i18n                                the English translation covers every source string
  pot                                 regenerate languages/webfiable-info.pot
  php74 [--list]                      no PHP 8.0-only function or syntax in shipped files
  samezip <verified> <deployed>       two zips hold the same files with the same bytes
  selftest                            proves that each check above can fail

Every check prints what it read and exits 1 on the first run that finds a problem.
"""

import sys

if sys.version_info < (3, 10):
    sys.stderr.write("release_checks.py needs Python 3.10 or newer\n")
    sys.exit(2)

import argparse  # noqa: E402
import difflib  # noqa: E402
import hashlib  # noqa: E402
import io  # noqa: E402
import json  # noqa: E402
import os  # noqa: E402
import re  # noqa: E402
import shutil  # noqa: E402
import subprocess  # noqa: E402
import tempfile  # noqa: E402
import urllib.request  # noqa: E402
import zipfile  # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SLUG = "webfiable-info"
TEXT_DOMAIN = "webfiable-info"
CORE_VERSION_URL = "https://api.wordpress.org/core/version-check/1.7/"
UPGRADE_NOTICE_MAX = 300

# The package, file by file. Each commit that adds or removes a shipped file
# edits this list in the same commit, so the check always describes the tree
# it runs on.
REQUIRED = [
    "webfiable-info.php",
    "uninstall.php",
    "readme.txt",
    "LICENSE",
    "includes/admin.php",
    "includes/constants.php",
    "includes/endpoint.php",
    "includes/i18n.php",
    "includes/logger.php",
    "includes/options.php",
    "includes/registration.php",
    "includes/routing.php",
    "includes/update.php",
    "assets/css/admin.css",
    "assets/css/notice.css",
    "assets/img/icon.png",
    "assets/img/webfiable-lockup-light.svg",
    "languages/webfiable-info.pot",
    "languages/webfiable-info-en_US.po",
    "languages/webfiable-info-en_US.mo",
]

# Development files that must never reach the package (matched per path segment).
FORBIDDEN_NAMES = [
    "composer.json",
    "composer.lock",
    "phpcs.xml",
    "README.md",
    "RELEASE-CHECKLIST.md",
    ".distignore",
    ".gitignore",
    ".gitattributes",
]
FORBIDDEN_DIRS = [".github", "bin", "tests", ".wordpress-org", "vendor", "node_modules"]
FORBIDDEN_SUFFIXES = [".zip"]

# PHP 8.0-only functions and syntax (the plugin declares Requires PHP: 7.4).
PHP80_DENY = [
    ("str_contains(", re.compile(r"(?<![\w$>:\\])str_contains\s*\(")),
    ("str_starts_with(", re.compile(r"(?<![\w$>:\\])str_starts_with\s*\(")),
    ("str_ends_with(", re.compile(r"(?<![\w$>:\\])str_ends_with\s*\(")),
    ("array_is_list(", re.compile(r"(?<![\w$>:\\])array_is_list\s*\(")),
    ("get_debug_type(", re.compile(r"(?<![\w$>:\\])get_debug_type\s*\(")),
    ("?->", re.compile(r"\?->")),
    ("match (", re.compile(r"(?<![\w$>:\\])match\s*\(")),
]

I18N_FUNCTIONS = (
    "__",
    "_e",
    "esc_html__",
    "esc_html_e",
    "esc_attr__",
    "esc_attr_e",
)


class CheckError(Exception):
    pass


# --------------------------------------------------------------------------- readers


def read(path):
    with open(path, encoding="utf-8") as fh:
        return fh.read()


def php_header(text):
    """The plugin header fields of a main PHP file (first docblock)."""
    fields = {}
    m = re.search(r"/\*\*(.*?)\*/", text, re.S)
    if not m:
        return fields
    for line in m.group(1).splitlines():
        line = line.strip().lstrip("*").strip()
        mm = re.match(r"([A-Za-z][A-Za-z ]+?):\s*(.+)$", line)
        if mm:
            fields[mm.group(1).strip()] = mm.group(2).strip()
    return fields


def readme_parts(text):
    """(name, header fields, sections) of a readme.txt."""
    lines = text.splitlines()
    name = None
    fields = {}
    if lines:
        m = re.match(r"^===\s*(.+?)\s*===\s*$", lines[0])
        if m:
            name = m.group(1)
    for line in lines[1:]:
        if line.startswith("=="):
            break
        mm = re.match(r"^([A-Za-z][A-Za-z ]+?):\s*(.+)$", line)
        if mm:
            fields[mm.group(1).strip()] = mm.group(2).strip()
    sections = {}
    current = None
    for line in lines:
        m = re.match(r"^==\s*(.+?)\s*==\s*$", line)
        if m and not line.startswith("==="):
            current = m.group(1)
            sections[current] = []
            continue
        if current is not None:
            sections[current].append(line)
    return name, fields, sections


def subsection(section_lines, version):
    """Lines under `= version =` inside a readme section, or None."""
    out = None
    for line in section_lines:
        m = re.match(r"^=\s*(.+?)\s*=\s*$", line)
        if m:
            if out is not None:
                break
            if m.group(1) == version:
                out = []
            continue
        if out is not None:
            out.append(line)
    return out


def constant_version(text):
    m = re.search(r"define\(\s*'WEBFIABLE_INFO_VERSION'\s*,\s*'([^']+)'\s*\)", text)
    return m.group(1) if m else None


def major_minor(version):
    m = re.match(r"^(\d+)\.(\d+)", version or "")
    if not m:
        raise CheckError(f"not a version: {version!r}")
    return int(m.group(1)), int(m.group(2))


def current_wordpress():
    """First offer of the core version-check API. Fails loudly on any network error."""
    try:
        req = urllib.request.Request(CORE_VERSION_URL, headers={"User-Agent": "webfiable-release-checks"})
        with urllib.request.urlopen(req, timeout=30) as resp:
            data = json.load(resp)
        return data["offers"][0]["current"]
    except Exception as exc:  # any failure is a failed check, never a pass
        raise CheckError(f"could not read the current WordPress version from {CORE_VERSION_URL}: {exc}")


# --------------------------------------------------------------------------- coherence


def check_coherence(root, tag=None, wordpress_current=None):
    """Returns the list of problems (empty = coherent)."""
    problems = []
    header = php_header(read(os.path.join(root, "webfiable-info.php")))
    const = constant_version(read(os.path.join(root, "includes", "constants.php")))
    name, fields, sections = readme_parts(read(os.path.join(root, "readme.txt")))

    version = header.get("Version")
    stable = fields.get("Stable tag")
    print(f"  header Version: {version}; WEBFIABLE_INFO_VERSION: {const}; readme Stable tag: {stable}")
    if not version or version != const or version != stable:
        problems.append(f"versions differ: header {version}, constants.php {const}, readme Stable tag {stable}")

    print(f"  header Plugin Name: {header.get('Plugin Name')}; readme name: {name}")
    if not header.get("Plugin Name") or header.get("Plugin Name") != name:
        problems.append(f"plugin names differ: header {header.get('Plugin Name')!r}, readme {name!r}")

    for key in ("Requires at least", "Requires PHP"):
        h, r = header.get(key), fields.get(key)
        print(f"  {key}: header {h}; readme {r}")
        if not h:
            problems.append(f"header has no «{key}»")
        elif h != r:
            problems.append(f"«{key}» differs: header {h}, readme {r}")

    for sec in ("Changelog", "Upgrade Notice"):
        body = subsection(sections.get(sec, []), version or "")
        print(f"  == {sec} == has = {version} =: {'yes' if body is not None else 'no'}")
        if body is None:
            problems.append(f"readme has no «= {version} =» under «== {sec} ==»")
        elif sec == "Upgrade Notice":
            notice = " ".join(line.strip() for line in body if line.strip())
            print(f"  upgrade notice {version}: {len(notice)} characters (max {UPGRADE_NOTICE_MAX})")
            if not notice:
                problems.append(f"upgrade notice {version} is empty")
            if len(notice) > UPGRADE_NOTICE_MAX:
                problems.append(f"upgrade notice {version} is {len(notice)} characters (max {UPGRADE_NOTICE_MAX})")

    if tag is not None:
        m = re.match(r"^v(\d+\.\d+\.\d+)$", tag)
        print(f"  tag: {tag}")
        if not m:
            problems.append(f"tag {tag!r} is not vX.Y.Z")
        elif m.group(1) != version:
            problems.append(f"tag {tag} names {m.group(1)}, the code says {version}")

    tested = fields.get("Tested up to")
    if wordpress_current is None:
        wordpress_current = current_wordpress()
    print(f"  readme Tested up to: {tested}; current WordPress: {wordpress_current}")
    try:
        if major_minor(tested) < major_minor(wordpress_current):
            problems.append(f"«Tested up to: {tested}» is below the current WordPress {wordpress_current}")
    except CheckError as exc:
        problems.append(str(exc))
    return problems


# --------------------------------------------------------------------------- zip


def check_zip(path, expect_version):
    problems = []
    try:
        zf = zipfile.ZipFile(path)
    except (OSError, zipfile.BadZipFile) as exc:
        return [f"cannot open {path}: {exc}"]
    with zf:
        names = sorted(n for n in zf.namelist() if not n.endswith("/"))
        print(f"  {len(names)} files in {os.path.basename(path)}:")
        for n in names:
            print(f"    {n}")
        prefix = SLUG + "/"
        outside = [n for n in names if not n.startswith(prefix)]
        for n in outside:
            problems.append(f"outside the {prefix} folder: {n}")
        inside = {n[len(prefix):] for n in names if n.startswith(prefix)}
        for req in REQUIRED:
            if req not in inside:
                problems.append(f"missing: {req}")
        for rel in sorted(inside):
            parts = rel.split("/")
            if any(p in FORBIDDEN_NAMES for p in parts) or any(p in FORBIDDEN_DIRS for p in parts[:-1]) \
                    or any(rel.endswith(s) for s in FORBIDDEN_SUFFIXES):
                problems.append(f"development file in the package: {rel}")
            elif rel not in REQUIRED:
                problems.append(f"not in the package list: {rel}")
        main = prefix + "webfiable-info.php"
        if main in names:
            packaged = php_header(zf.read(main).decode("utf-8")).get("Version")
            print(f"  packaged header Version: {packaged}; expected: {expect_version}")
            if packaged != expect_version:
                problems.append(f"packaged version {packaged} differs from {expect_version}")
    return problems


# --------------------------------------------------------------------------- samezip


def zip_digests(path, label, problems):
    """{entry name: sha256 of its bytes, or None for a directory entry}."""
    out = {}
    with zipfile.ZipFile(path) as zf:
        for info in zf.infolist():
            if info.filename in out:
                problems.append(f"duplicate entry in the {label} zip: {info.filename}")
                continue
            out[info.filename] = None if info.is_dir() else hashlib.sha256(zf.read(info)).hexdigest()
    return out


def check_samezip(verified, deployed):
    """The zip `verify` inspected and the zip `deploy` built hold the same entries with the
    same bytes (names and per-file sha256; timestamps and compression are ignored)."""
    problems = []
    try:
        a = zip_digests(verified, "verified", problems)
        b = zip_digests(deployed, "deployed", problems)
    except (OSError, zipfile.BadZipFile) as exc:
        return [f"cannot open a zip: {exc}"]
    print(f"  verified: {len(a)} entries; deployed: {len(b)} entries")
    if not a or not b:
        problems.append("a zip has no entries")
    for name in sorted(set(a) | set(b)):
        if name not in b:
            problems.append(f"only in the verified zip: {name}")
        elif name not in a:
            problems.append(f"only in the deployed zip: {name}")
        elif a[name] != b[name]:
            problems.append(f"content differs: {name}")
        else:
            print(f"    same  {(a[name] or 'directory')[:16]}  {name}")
    return problems


# --------------------------------------------------------------------------- i18n


def php_unescape(s, quote):
    if quote == "'":
        return s.replace("\\'", "'").replace("\\\\", "\\")
    return re.sub(r'\\([\\"$nt])', lambda m: {"n": "\n", "t": "\t"}.get(m.group(1), m.group(1)), s)


def extract_msgids(root):
    """[(msgid, file)] in source order: header Name/Description, then every call."""
    out = []
    seen = set()
    header = php_header(read(os.path.join(root, "webfiable-info.php")))
    for key, comment in (("Plugin Name", "Plugin Name of the plugin"), ("Description", "Description of the plugin")):
        if header.get(key) and header[key] not in seen:
            seen.add(header[key])
            out.append((header[key], "#. " + comment))
    call = re.compile(
        r"(?<![\w$>:])(" + "|".join(I18N_FUNCTIONS) + r")\(\s*"
        r"('(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\")\s*,\s*'" + re.escape(TEXT_DOMAIN) + r"'\s*\)",
        re.S,
    )
    for rel in shipped_php():
        text = read(os.path.join(root, rel))
        for m in call.finditer(text):
            lit = m.group(2)
            msgid = php_unescape(lit[1:-1], lit[0])
            if msgid not in seen:
                seen.add(msgid)
                out.append((msgid, "#: " + rel))
    return out


def po_escape(s):
    return s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n").replace("\t", "\\t")


def po_unescape(s):
    out = []
    i = 0
    while i < len(s):
        c = s[i]
        if c == "\\" and i + 1 < len(s):
            n = s[i + 1]
            out.append({"n": "\n", "t": "\t", '"': '"', "\\": "\\"}.get(n, n))
            i += 2
        else:
            out.append(c)
            i += 1
    return "".join(out)


def parse_po(text):
    """[{msgid, msgstr, fuzzy}] (header entry included, msgid '')."""
    entries = []
    cur = None
    field = None
    fuzzy = False
    for raw in text.splitlines() + [""]:
        line = raw.strip()
        if not line:
            if cur is not None:
                entries.append(cur)
            cur, field, fuzzy = None, None, False
            continue
        if line.startswith("#,") and "fuzzy" in line:
            fuzzy = True
            continue
        if line.startswith("#"):
            continue
        m = re.match(r'^(msgid|msgstr)\s+"(.*)"$', line)
        if m:
            if cur is None:
                cur = {"msgid": "", "msgstr": "", "fuzzy": fuzzy}
            field = m.group(1)
            cur[field] = po_unescape(m.group(2))
            continue
        m = re.match(r'^"(.*)"$', line)
        if m and cur is not None and field:
            cur[field] += po_unescape(m.group(1))
            continue
        raise CheckError(f"unreadable .po line: {raw!r}")
    return entries


def check_po_complete(po_text, wanted):
    problems = []
    entries = parse_po(po_text)
    by_id = {e["msgid"]: e for e in entries if e["msgid"]}
    for msgid in wanted:
        if msgid not in by_id:
            problems.append(f"missing from the translation: {msgid!r}")
    for e in entries:
        if not e["msgid"]:
            continue
        if e["fuzzy"]:
            problems.append(f"fuzzy: {e['msgid']!r}")
        if not e["msgstr"]:
            problems.append(f"untranslated: {e['msgid']!r}")
    print(f"  {len(by_id)} entries, {len(wanted)} source strings")
    return problems


def render_pot(root, entries):
    header = php_header(read(os.path.join(root, "webfiable-info.php")))
    head = [
        "# Copyright (C) 2024-2026 Webfiable",
        "# This file is distributed under the GPLv3 or later.",
        'msgid ""',
        'msgstr ""',
        f'"Project-Id-Version: {po_escape(header.get("Plugin Name", ""))} {header.get("Version", "")}\\n"',
        '"Report-Msgid-Bugs-To: https://webfiable.com\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Language: \\n"',
        '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
        "",
    ]
    body = []
    for msgid, ref in entries:
        body += [ref, f'msgid "{po_escape(msgid)}"', 'msgstr ""', ""]
    return "\n".join(head + body)


def check_i18n(root):
    problems = []
    wanted = [m for m, _ in extract_msgids(root)]
    po_path = os.path.join(root, "languages", "webfiable-info-en_US.po")
    mo_path = os.path.join(root, "languages", "webfiable-info-en_US.mo")
    pot_path = os.path.join(root, "languages", "webfiable-info.pot")
    if not os.path.exists(po_path):
        return [f"missing: {os.path.relpath(po_path, root)}"]
    problems += check_po_complete(read(po_path), wanted)
    pot_ids = [e["msgid"] for e in parse_po(read(pot_path)) if e["msgid"]]
    if sorted(pot_ids) != sorted(wanted):
        extra = sorted(set(pot_ids) - set(wanted))
        missing = sorted(set(wanted) - set(pot_ids))
        problems.append(f".pot differs from a fresh extraction (run `pot`): extra {extra}, missing {missing}")
    if shutil.which("msgfmt") and shutil.which("msgunfmt"):
        r = subprocess.run(["msgfmt", "--check", "-o", "-", po_path], capture_output=True)
        if r.returncode != 0:
            problems.append("msgfmt --check failed: " + r.stderr.decode("utf-8", "replace").strip())
        else:
            fresh_run = subprocess.run(["msgunfmt", "-"], input=r.stdout, capture_output=True)
            committed_run = subprocess.run(["msgunfmt", mo_path], capture_output=True)
            fresh, committed = fresh_run.stdout, committed_run.stdout
            if fresh != committed:
                for label, run in (("committed .mo", committed_run), ("msgfmt(.po)", fresh_run)):
                    err = run.stderr.decode("utf-8", "replace").strip()
                    print(f"    msgunfmt {label}: exit {run.returncode}, {len(run.stdout)} bytes" + (f", stderr: {err[:300]}" if err else ""))
                diff = list(difflib.unified_diff(
                    committed.decode("utf-8", "replace").splitlines(),
                    fresh.decode("utf-8", "replace").splitlines(),
                    "msgunfmt committed .mo", "msgunfmt msgfmt(.po)", lineterm="", n=1))
                for line in diff[:60]:
                    print("    " + line)
                problems.append(f"the committed .mo is not the compiled .po ({len(diff)} diff lines)")
    elif os.environ.get("CI"):
        problems.append("GNU gettext (msgfmt, msgunfmt) is required in CI")
    else:
        print("  gettext not installed here: msgfmt/.mo checks skipped (CI runs them)")
    return problems


# --------------------------------------------------------------------------- php74


def shipped_php():
    return [p for p in REQUIRED if p.endswith(".php")]


def check_php74(root, files=None):
    problems = []
    files = files if files is not None else shipped_php()
    for rel in files:
        for lineno, line in enumerate(read(os.path.join(root, rel)).splitlines(), 1):
            for label, rx in PHP80_DENY:
                if rx.search(line):
                    problems.append(f"{rel}:{lineno}: PHP 8.0-only «{label}»")
    print(f"  {len(files)} shipped PHP files scanned")
    return problems


# --------------------------------------------------------------------------- selftest

FIXTURE_HEADER = """<?php
/**
 * Plugin Name: Fixture Plugin
 * Description: Fixture.
 * Version: 2.2.0
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Text Domain: webfiable-info
 */
echo esc_html__( 'Hola', 'webfiable-info' );
"""
FIXTURE_CONSTANTS = "<?php\ndefine( 'WEBFIABLE_INFO_VERSION', '2.2.0' );\n"
FIXTURE_README = """=== Fixture Plugin ===
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0

Fixture.

== Changelog ==

= 2.2.0 =
* Fixture.

== Upgrade Notice ==

= 2.2.0 =
{notice}
"""
FIXTURE_PO = """msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"

msgid "Fixture Plugin"
msgstr "Fixture Plugin"

msgid "Fixture."
msgstr "Fixture."

msgid "Hola"
msgstr "{hola}"
"""


def write_fixture(d, header=FIXTURE_HEADER, constants=FIXTURE_CONSTANTS, readme=None):
    os.makedirs(os.path.join(d, "includes"), exist_ok=True)
    with open(os.path.join(d, "webfiable-info.php"), "w", encoding="utf-8") as fh:
        fh.write(header)
    with open(os.path.join(d, "includes", "constants.php"), "w", encoding="utf-8") as fh:
        fh.write(constants)
    with open(os.path.join(d, "readme.txt"), "w", encoding="utf-8") as fh:
        fh.write(readme if readme is not None else FIXTURE_README.format(notice="Fixture."))


def fixture_zip(drop_prefix=None, extra=None, version="2.2.0"):
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w") as zf:
        for rel in REQUIRED:
            if drop_prefix and rel.startswith(drop_prefix):
                continue
            body = FIXTURE_HEADER.replace("2.2.0", version) if rel == "webfiable-info.php" else "x"
            zf.writestr(f"{SLUG}/{rel}", body)
        for rel in extra or []:
            zf.writestr(f"{SLUG}/{rel}", "x")
    return buf.getvalue()


def selftest():
    results = []

    def case(label, expect_fail, fn, reason=None):
        # `reason`: the failure must also carry this exact problem line. Without it a case
        # whose fixture trips two layers (the forbidden list AND the exact package list) passes
        # when either layer is removed, so one of them has no falsifier (review round 1, R1-9).
        print(f"- {label}")
        try:
            problems = fn()
        except CheckError as exc:
            problems = [str(exc)]
        failed = bool(problems)
        for p in problems:
            print(f"    finds: {p}")
        ok = failed == expect_fail
        if ok and reason is not None and reason not in problems:
            ok = False
            print(f"    WRONG: the reason «{reason}» is not among the problems found")
        print(f"    {'ok' if ok else 'WRONG'}: expected {'exit 1' if expect_fail else 'exit 0'}, got {'exit 1' if failed else 'exit 0'}")
        results.append(ok)

    tmp = tempfile.mkdtemp(prefix="release-checks-")
    try:
        def coh(**kw):
            d = tempfile.mkdtemp(dir=tmp)
            write_fixture(d, **{k: v for k, v in kw.items() if k in ("header", "constants", "readme")})
            return check_coherence(d, tag=kw.get("tag"), wordpress_current=kw.get("wp", "7.1.2"))

        case("coherence: the coherent fixture passes (control)", False, lambda: coh())
        case("coherence: constants.php at 2.2.1", True,
             lambda: coh(constants=FIXTURE_CONSTANTS.replace("2.2.0", "2.2.1")))
        case("coherence: Tested up to 7.0 against the offer 7.1.2", True,
             lambda: coh(readme=FIXTURE_README.format(notice="Fixture.").replace("Tested up to: 7.1", "Tested up to: 7.0")))
        case("coherence: header Requires at least 5.3, readme 4.7", True,
             lambda: coh(readme=FIXTURE_README.format(notice="Fixture.").replace("Requires at least: 5.3", "Requires at least: 4.7")))
        case("coherence: header without Requires PHP", True,
             lambda: coh(header=FIXTURE_HEADER.replace(" * Requires PHP: 7.4\n", "")))
        case("coherence: readme name differs from the header", True,
             lambda: coh(readme=FIXTURE_README.format(notice="Fixture.").replace("=== Fixture Plugin ===", "=== Other ===")))
        case("coherence: no = 2.2.0 = under Changelog", True,
             lambda: coh(readme=FIXTURE_README.format(notice="Fixture.").replace("== Changelog ==\n\n= 2.2.0 =", "== Changelog ==\n\n= 2.1.9 =")))
        case("coherence: upgrade notice of 300 characters (control)", False,
             lambda: coh(readme=FIXTURE_README.format(notice="a" * 300)))
        case("coherence: upgrade notice of 301 characters", True,
             lambda: coh(readme=FIXTURE_README.format(notice="a" * 301)))
        case("coherence: --tag v2.2.0 (control)", False, lambda: coh(tag="v2.2.0"))
        case("coherence: --tag v2.2.1 against code 2.2.0", True, lambda: coh(tag="v2.2.1"))

        def zcheck(data, expect="2.2.0"):
            p = os.path.join(tmp, "fixture.zip")
            with open(p, "wb") as fh:
                fh.write(data)
            return check_zip(p, expect)

        case("zip: the package list, exactly (control)", False, lambda: zcheck(fixture_zip()))
        case("zip: with composer.json", True, lambda: zcheck(fixture_zip(extra=["composer.json"])),
             reason="development file in the package: composer.json")
        case("zip: without languages/", True, lambda: zcheck(fixture_zip(drop_prefix="languages/")))
        case("zip: with tests/stubs.php", True, lambda: zcheck(fixture_zip(extra=["tests/stubs.php"])),
             reason="development file in the package: tests/stubs.php")
        case("zip: with a file not in the package list", True, lambda: zcheck(fixture_zip(extra=["notes.txt"])))
        case("zip: packaged 2.2.0, expected 2.2.1", True, lambda: zcheck(fixture_zip(), expect="2.2.1"))

        def zpair(deployed_change=None, deployed_extra=None):
            # Two builds of the same files at different times, as verify and deploy make them.
            paths = []
            for n, stamp in ((0, (2026, 9, 25, 10, 0, 0)), (1, (2026, 9, 25, 10, 7, 30))):
                p = os.path.join(tmp, f"pair-{n}.zip")
                with zipfile.ZipFile(p, "w", zipfile.ZIP_DEFLATED) as zf:
                    zf.writestr(zipfile.ZipInfo(f"{SLUG}/", stamp), "")
                    for rel in REQUIRED:
                        body = FIXTURE_HEADER if rel == "webfiable-info.php" else "x " + rel
                        if n == 1 and rel == deployed_change:
                            body += " changed"
                        zf.writestr(zipfile.ZipInfo(f"{SLUG}/{rel}", stamp), body)
                    if n == 1 and deployed_extra:
                        zf.writestr(zipfile.ZipInfo(f"{SLUG}/{deployed_extra}", stamp), "x")
                paths.append(p)
            return check_samezip(*paths)

        case("samezip: the same files built at two times (control)", False, lambda: zpair())
        case("samezip: one file differs in the deployed zip", True, lambda: zpair(deployed_change="readme.txt"),
             reason=f"content differs: {SLUG}/readme.txt")
        case("samezip: a file only in the deployed zip", True, lambda: zpair(deployed_extra="notes.txt"),
             reason=f"only in the deployed zip: {SLUG}/notes.txt")

        wanted = ["Fixture Plugin", "Fixture.", "Hola"]
        case("i18n: a complete .po (control)", False,
             lambda: check_po_complete(FIXTURE_PO.format(hola="Hello"), wanted))
        case("i18n: a .po with one empty msgstr", True,
             lambda: check_po_complete(FIXTURE_PO.format(hola=""), wanted))
        case("i18n: a fuzzy entry", True,
             lambda: check_po_complete(FIXTURE_PO.format(hola="Hello").replace('msgid "Hola"', '#, fuzzy\nmsgid "Hola"'), wanted))
        case("i18n: a source string missing from the .po", True,
             lambda: check_po_complete(FIXTURE_PO.format(hola="Hello"), wanted + ["Adiós"]))

        def php(src):
            d = tempfile.mkdtemp(dir=tmp)
            with open(os.path.join(d, "f.php"), "w", encoding="utf-8") as fh:
                fh.write(src)
            return check_php74(d, ["f.php"])

        case("php74: PHP 7.4 code (control)", False,
             lambda: php("<?php\nif ( false !== strpos( $a, 'b' ) ) { webfiable_str_contains( $a ); }\n"))
        case("php74: str_contains(", True, lambda: php("<?php\nif ( str_contains( $a, 'b' ) ) {}\n"))
        case("php74: nullsafe ?->", True, lambda: php("<?php\n$x = $a?->b;\n"))
        case("php74: match (", True, lambda: php("<?php\necho match (1) { 1 => 'a' };\n"))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)

    bad = results.count(False)
    print(f"selftest: {len(results) - bad}/{len(results)} cases behaved as expected")
    return [] if bad == 0 else [f"{bad} self-test case(s) did not behave as expected"]


# --------------------------------------------------------------------------- main


def finish(name, problems):
    if problems:
        for p in problems:
            print(f"FAIL {name}: {p}")
        print(f"{name}: FAIL ({len(problems)})")
        return 1
    print(f"{name}: OK")
    return 0


def main(argv):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    c = sub.add_parser("coherence")
    c.add_argument("--tag")
    z = sub.add_parser("zip")
    z.add_argument("path")
    z.add_argument("--expect-version")
    sub.add_parser("i18n")
    sub.add_parser("pot")
    p = sub.add_parser("php74")
    p.add_argument("--list", action="store_true", help="print the shipped PHP files, one per line")
    sub.add_parser("selftest")
    s = sub.add_parser("samezip")
    s.add_argument("verified")
    s.add_argument("deployed")
    args = ap.parse_args(argv)

    try:
        if args.cmd == "coherence":
            return finish("coherence", check_coherence(ROOT, tag=args.tag))
        if args.cmd == "zip":
            expect = args.expect_version or php_header(read(os.path.join(ROOT, "webfiable-info.php"))).get("Version")
            return finish("zip", check_zip(args.path, expect))
        if args.cmd == "i18n":
            return finish("i18n", check_i18n(ROOT))
        if args.cmd == "pot":
            entries = extract_msgids(ROOT)
            with open(os.path.join(ROOT, "languages", "webfiable-info.pot"), "w", encoding="utf-8") as fh:
                fh.write(render_pot(ROOT, entries))
            print(f"pot: {len(entries)} msgids written")
            return 0
        if args.cmd == "php74":
            if args.list:
                print("\n".join(shipped_php()))
                return 0
            return finish("php74", check_php74(ROOT))
        if args.cmd == "selftest":
            return finish("selftest", selftest())
        if args.cmd == "samezip":
            return finish("samezip", check_samezip(args.verified, args.deployed))
    except CheckError as exc:
        print(f"FAIL {args.cmd}: {exc}")
        return 1
    return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

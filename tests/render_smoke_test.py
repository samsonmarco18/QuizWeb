"""Test real HTTPS deployment accounts. Does not create accounts or change grades."""
import argparse
import http.cookiejar
import json
import re
import urllib.error
import urllib.parse
import urllib.request


def fetch(opener, url, data=None):
    response = opener.open(url, data=data, timeout=60)
    if response.status != 200:
        raise AssertionError(f"Unexpected HTTP {response.status}: {response.url}")
    return response.url, response.read().decode("utf-8")


def check_account(base, email, password, role):
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
    )
    _, page = fetch(opener, base + "login.php")
    token = re.search(r'name="csrf" value="([^"]+)"', page)
    if not token:
        raise AssertionError("Login CSRF field is missing")
    body = urllib.parse.urlencode(
        {"csrf": token[1], "email": email, "password": password}
    ).encode()
    url, page = fetch(opener, base + "login.php", body)
    expected = "admin.php" if role == "admin" else "dashboard.php"
    if not urllib.parse.urlsplit(url).path.endswith("/" + expected):
        raise AssertionError(f"{role} login failed: {email}")
    if "Invalid email or password" in page or "Fatal error" in page:
        raise AssertionError(f"{role} login returned an error")
    if role == "admin":
        _, users = fetch(opener, base + "admin.php?tab=users")
        for sample in ["ava.mendoza@chalk.demo", "elena.cruz@chalk.demo"]:
            if sample not in users:
                raise AssertionError(f"Persisted sample user missing: {sample}")
        if "System administration" not in page:
            raise AssertionError("Administrator dashboard missing")
    else:
        if "Grade 10 Science" not in page:
            raise AssertionError(f"{role} sample classroom missing")
        # A signed-in sample user must never gain admin access.
        try:
            _, denied = fetch(opener, base + "admin.php")
        except urllib.error.HTTPError as error:
            if error.code != 403:
                raise
        else:
            if "System administration" in denied:
                raise AssertionError(f"{role} can access administration")
    fetch(opener, base + "logout.php")
    print(f"PASS: {role} sign-in, persisted data, permissions, and logout ({email})")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default="https://chalk-web.onrender.com/QuizWeb/")
    parser.add_argument("--revision", help="Require this deployed Git commit")
    parser.add_argument("--skip-health", action="store_true", help="Baseline check of the old deployment")
    args = parser.parse_args()
    base = args.base_url.rstrip("/") + "/"
    parsed = urllib.parse.urlsplit(base)
    if parsed.scheme != "https" or parsed.hostname in [None, "localhost", "127.0.0.1", "::1"]:
        raise AssertionError("Use the public HTTPS deployment URL")
    if not args.skip_health:
        _, raw = fetch(urllib.request.build_opener(), base + "health.php")
        health = json.loads(raw)
        if health.get("status") != "ok" or health.get("storage") != "postgresql" or health.get("environment") != "render":
            raise AssertionError("Render PostgreSQL health check failed")
        if args.revision and health.get("revision") != args.revision:
            raise AssertionError(f"Old deployed revision: {health.get('revision')}")
        print(f"PASS: Render PostgreSQL health and revision ({health.get('revision')})")
    for role, email, password in [
        ("admin", "admin@chalk.local", "ChalkAdmin!2026"),
        ("teacher", "elena.cruz@chalk.demo", "Sample123!"),
        ("student", "ava.mendoza@chalk.demo", "Sample123!"),
    ]:
        check_account(base, email, password, role)


if __name__ == "__main__":
    main()

"""Live Render test using sample accounts and a reusable ungraded image demo.

Creates Image Match Demo in the sample Science classroom if absent, then writes
two sample practice attempts (one disqualified zero and one correctly matched).
It preserves existing quizzes and does not configure or publish academic grades.
"""
import argparse
import base64
import html
import http.cookiejar
import json
import re
import struct
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zlib

BASE = "https://chalk-web.onrender.com/QuizWeb/"


def request(client, route, fields=None):
    body = urllib.parse.urlencode(fields).encode() if fields is not None else None
    with client.open(BASE + route, data=body, timeout=60) as response:
        return response.url, response.read().decode()


def session(email):
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    _, page = request(client, "login.php")
    token = re.search(r'name="csrf" value="([^"]+)"', page)[1]
    url, _ = request(client, "login.php", {"csrf": token, "email": email, "password": "Sample123!"})
    assert url.endswith("dashboard.php"), "Sample sign-in failed"
    return client


def raster(red, green, blue):
    def chunk(kind, data):
        return struct.pack(">I", len(data)) + kind + data + struct.pack(">I", zlib.crc32(kind + data) & 0xFFFFFFFF)
    width, height = 160, 100
    pixels = (b"\x00" + bytes([red, green, blue]) * width) * height
    image = b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0))
    image += chunk(b"IDAT", zlib.compress(pixels)) + chunk(b"IEND", b"")
    return "data:image/png;base64," + base64.b64encode(image).decode()


def cards(page):
    return re.findall(r'<article class="quiz-card\b[^>]*>(.*?)</article>', page, re.S)


def quiz_id(card):
    match = re.search(r'play\.php\?classroom_id=\d+(?:&|&amp;)quiz_id=(\d+)', card)
    return int(match[1])


def play(client, classroom, quiz):
    _, page = request(client, f"play.php?classroom_id={classroom}&quiz_id={quiz}")
    data = json.loads(html.unescape(re.search(r"data-quiz='([^']+)'", page)[1]))
    fields = {}
    for attr, key in [("classroom-id", "classroom_id"), ("run-token", "run_token"), ("csrf", "csrf")]:
        fields[key] = html.unescape(re.search(f'data-{attr}="([^"]*)"', page)[1])
    fields["quiz_id"] = str(data["id"])
    assert 'assets/js/quiz-integrity.js' in page, "Shared security asset missing"
    assert 'data-integrity-url="/QuizWeb/quiz_integrity.php"' in page
    return data, fields


def event(client, fields, action, reason="", event_id=None):
    _, raw = request(client, "quiz_integrity.php", dict(fields, action=action, reason=reason, event_id=event_id or uuid.uuid4().hex))
    return json.loads(raw)


def expect_status(client, route, fields, status):
    try:
        request(client, route, fields)
    except urllib.error.HTTPError as error:
        assert error.code == status, f"Expected HTTP {status}, got {error.code}"
    else:
        raise AssertionError(f"Expected HTTP {status}")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--revision", required=True)
    args = parser.parse_args()
    _, raw = request(urllib.request.build_opener(), "health.php")
    health = json.loads(raw)
    assert health["environment"] == "render" and health["storage"] == "postgresql" and health["revision"] == args.revision
    teacher = session("elena.cruz@chalk.demo")
    _, dashboard = request(teacher, "dashboard.php")
    classroom = None
    for candidate in dict.fromkeys(re.findall(r'classroom\.php\?id=(\d+)', dashboard)):
        _, page = request(teacher, f"classroom.php?id={candidate}&tab=quizzes")
        if "Grade 10 Science" in page:
            classroom, listing = int(candidate), page
            break
    assert classroom, "Sample Science classroom missing"
    demo = next((card for card in cards(listing) if ">Image Match Demo<" in card), None)
    if not demo:
        route = f"quiz_builder.php?classroom_id={classroom}&game_type=flip_match"
        _, builder = request(teacher, route)
        csrf = re.search(r'name="csrf" value="([^"]+)"', builder)[1]
        pairs = [
            {"prompt": "Red color image", "answer": "Red", "prompt_image": raster(235, 65, 65), "points": 10},
            {"prompt": "Blue color image", "answer": "Blue matching image", "prompt_image": raster(35, 105, 230), "answer_image": raster(35, 105, 230), "points": 10},
        ]
        payload = {"csrf": csrf, "title": "Image Match Demo", "description": "Practice matching text and images. Fullscreen and the three-warning rule apply to student runs.",
                   "game_type": "flip_match", "mastery_threshold": "75", "grade_category_id": "", "grade_attempt_policy": "highest",
                   "grade_max_score": "", "due_at": "", "questions_payload": json.dumps(pairs)}
        _, preview = request(teacher, route, dict(payload, action="preview"))
        prepared = json.loads(preview)
        assert not prepared["errors"] and prepared["questions"][1]["answer_image"].startswith("data:image/png;base64,")
        request(teacher, route, payload)
        _, listing = request(teacher, f"classroom.php?id={classroom}&tab=quizzes")
        demo = next((card for card in cards(listing) if ">Image Match Demo<" in card), None)
    assert demo, "Image matching demo did not persist"
    matching_id = quiz_id(demo)
    original, _ = play(teacher, classroom, matching_id)
    student = session("ava.mendoza@chalk.demo")
    shuffled, fields = play(student, classroom, matching_id)
    assert [q["id"] for q in shuffled["questions"]] != [q["id"] for q in original["questions"]], "Student questions were not jumbled"
    assert sum(bool(q.get("prompt_image")) for q in shuffled["questions"]) == 2
    assert sum(bool(q.get("answer_image")) for q in shuffled["questions"]) == 1
    expect_status(student, "quiz_integrity.php", dict(fields, csrf="invalid", action="start", event_id=uuid.uuid4().hex), 403)
    expect_status(student, "quiz_integrity.php", dict(fields, classroom_id="999999", action="start", event_id=uuid.uuid4().hex), 403)
    expect_status(student, "submit_game.php", dict(fields, answers="{}"), 403)
    event(student, fields, "start")
    first_id = uuid.uuid4().hex
    first = event(student, fields, "violation", "screenshot_shortcut", first_id)
    assert first["warnings"] == 1 and not first["disqualified"]
    assert event(student, fields, "violation", "screenshot_shortcut", first_id)["warnings"] == 1
    for count, reason in [(2, "focus_loss"), (3, "fullscreen_exit"), (4, "tab_hidden")]:
        status = event(student, fields, "violation", reason)
        assert status["warnings"] == count and status["disqualified"] == (count == 4)
    _, zero = request(student, status["results_url"].split("/QuizWeb/", 1)[1])
    assert "0 / 20 points" in zero and "Warnings recorded: 4" in zero
    # Correct answers and a forged disqualified=false cannot revive the closed run.
    results_url, _ = request(student, "submit_game.php", dict(fields, answers=json.dumps({i: q["answer"] for i, q in enumerate(shuffled["questions"])}), disqualified="0"))
    assert results_url.endswith(status["results_url"])
    fresh, fields = play(student, classroom, matching_id)
    event(student, fields, "start")
    event(student, fields, "violation", "screenshot_shortcut")
    answers = {i: q["answer"] for i, q in enumerate(fresh["questions"])}
    answers.update({"_moves": 2, "_violations": 0, "_disqualified": True, "_quiz_snapshot": {}})
    normal_url, result = request(student, "submit_game.php", dict(fields, answers=json.dumps(answers), elapsed_seconds="15"))
    assert "20 / 20 points" in result and "Warnings recorded: 1" in result and "Detected screenshot shortcut" in result
    replay_url, _ = request(student, "submit_game.php", dict(fields, answers=json.dumps(answers), elapsed_seconds="15"))
    assert normal_url == replay_url, "Retried submit created a second attempt"
    request(student, "logout.php"); request(teacher, "logout.php")
    print(f"PASS: Render revision {args.revision}, persisted Image Match Demo (class {classroom}, quiz {matching_id}), both image faces, jumbled questions, CSRF/run protections, warning retries, fourth-warning zero, authoritative metadata, correct matching grade, and submit deduplication.")
    print(f"Image demo: {BASE}play.php?classroom_id={classroom}&quiz_id={matching_id}")


if __name__ == "__main__":
    main()

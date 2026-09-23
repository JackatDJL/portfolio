#!/usr/bin/env python3
import base64
import hashlib
import hmac
import os
import pty
import re
import select
import subprocess
import time
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "scripts" / "derive-route-key"
TEST_MASTER = "test-only-master-for-route-key-checks"


def run(*args, master=TEST_MASTER):
    environment = os.environ.copy()
    environment.pop("JACK_HOMEPAGE_MASTER_KEY", None)
    if master is not None:
        environment["JACK_HOMEPAGE_MASTER_KEY"] = master
    return subprocess.run(
        [str(SCRIPT), *args], cwd=ROOT, env=environment,
        text=True, capture_output=True, start_new_session=True,
    )


def expected_key(scope, route, master=TEST_MASTER):
    namespace = f"jack-homepage:v1:{scope}"
    digest = hmac.new(master.encode(), f"{namespace}:{route}".encode(), hashlib.sha256).digest()
    return base64.urlsafe_b64encode(digest).decode().rstrip("=")


def check_hidden_prompt():
    pid, terminal = pty.fork()
    if pid == 0:
        environment = os.environ.copy()
        environment.pop("JACK_HOMEPAGE_MASTER_KEY", None)
        os.execve(str(SCRIPT), [str(SCRIPT), "--raw", "cv/jobmesse-26"], environment)

    output = bytearray()
    sent = False
    child_status = None
    deadline = time.monotonic() + 10
    while time.monotonic() < deadline:
        ready, _, _ = select.select([terminal], [], [], 0.1)
        if ready:
            try:
                chunk = os.read(terminal, 4096)
            except OSError:
                break
            if not chunk:
                break
            output.extend(chunk)
            if not sent and b"Master key: " in output:
                os.write(terminal, TEST_MASTER.encode() + b"\n")
                sent = True
        waited, status = os.waitpid(pid, os.WNOHANG)
        if waited:
            child_status = status
            break

    if child_status is None:
        _, child_status = os.waitpid(pid, 0)
    os.close(terminal)
    if not sent or child_status != 0:
        raise AssertionError("hidden terminal prompt did not complete successfully")
    if TEST_MASTER.encode() in output:
        raise AssertionError("the hidden master key was echoed")
    if expected_key("cv", "jobmesse-26").encode() not in output:
        raise AssertionError("the hidden prompt returned the wrong key")


def main():
    first = run("--raw", "cv/jobmesse-26")
    same = run("--raw", "cv/jobmesse-26")
    changed_route = run("--raw", "cv/jobmesse-27")
    changed_scope = run("--raw", "--scope", "release", "cv/jobmesse-26")
    for result in (first, same, changed_route, changed_scope):
        if result.returncode:
            raise AssertionError(result.stderr)

    key = first.stdout.strip()
    if key != same.stdout.strip() or key != expected_key("cv", "jobmesse-26"):
        raise AssertionError("same master, scope, and route must derive the same key")
    if key == changed_route.stdout.strip() or key == changed_scope.stdout.strip():
        raise AssertionError("changing route or scope must change the key")
    if not re.fullmatch(r"[A-Za-z0-9_-]{43}", key):
        raise AssertionError("derived key must be unpadded base64url")
    if first.stdout != f"{key}\n":
        raise AssertionError("--raw must print only the key")

    labelled = run("cv/jobmesse-26")
    if "Route:\n  cv/jobmesse-26" not in labelled.stdout:
        raise AssertionError("labelled output should include the route")
    if "Namespace:\n  jack-homepage:v1:cv" not in labelled.stdout:
        raise AssertionError("labelled output should include the namespace")
    if f"Derived key:\n  {key}" not in labelled.stdout:
        raise AssertionError("labelled output should include the key")

    missing = run(master=None)
    if missing.returncode == 0 or "route is required" not in missing.stderr:
        raise AssertionError("missing routes should fail clearly")
    non_tty = run("--raw", "cv/jobmesse-26", master=None)
    if non_tty.returncode == 0 or "no terminal is available" not in non_tty.stderr:
        raise AssertionError("no-tty use should fail clearly without a master")

    check_hidden_prompt()
    print("derive-route-key checks passed")


if __name__ == "__main__":
    main()

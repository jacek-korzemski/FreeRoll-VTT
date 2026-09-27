#!/usr/bin/env python3
"""Set SEO= in the Table Manager .env already on the FTP server.

The deploy upload leaves .env in place. This step is the only writer of that
flag, driven by the GitHub secret SEO (true or false). An empty secret does
not change the file.
"""

import os
import re
import sys
from ftplib import FTP, FTP_TLS, error_perm
from io import BytesIO

SEO_LINE = re.compile(r"^\s*SEO\s*=")


def normalize_seo(raw: str) -> str | None:
    value = raw.strip().lower()
    if value == "":
        return None
    if value in ("1", "true", "yes", "on"):
        return "true"
    if value in ("0", "false", "no", "off"):
        return "false"
    print(f"Secret SEO must be true or false, got {raw!r}.", file=sys.stderr)
    sys.exit(1)


def rewrite_env(text: str, seo: str) -> str:
    newline = "\r\n" if "\r\n" in text else "\n"
    lines = text.splitlines()
    found = False
    rewritten = []
    for line in lines:
        if SEO_LINE.match(line):
            if not found:
                rewritten.append(f"SEO={seo}")
                found = True
            continue
        rewritten.append(line)
    if not found:
        rewritten.append(f"SEO={seo}")
    body = newline.join(rewritten)
    if text.endswith(("\n", "\r\n")) or body:
        if not body.endswith("\n"):
            body += newline
    return body


def connect():
    host = os.environ["FTP_SERVER"]
    user = os.environ["FTP_USERNAME"]
    password = os.environ["FTP_PASSWORD"]
    directory = os.environ["FTP_SERVER_DIR"]
    protocol = os.environ.get("FTP_PROTOCOL", "ftp").strip().lower() or "ftp"

    if protocol == "ftps":
        ftp = FTP_TLS(host, timeout=120)
        ftp.login(user, password)
        ftp.prot_p()
    elif protocol == "ftp":
        ftp = FTP(host, timeout=120)
        ftp.login(user, password)
    else:
        print(f"FTP_PROTOCOL must be ftp or ftps, got {protocol!r}.", file=sys.stderr)
        sys.exit(1)

    ftp.cwd(directory)
    return ftp


def main() -> None:
    seo = normalize_seo(os.environ.get("SEO", ""))
    if seo is None:
        print("Secret SEO is empty. Remote .env was left unchanged.")
        return

    ftp = connect()
    try:
        buf = BytesIO()
        try:
            ftp.retrbinary("RETR .env", buf.write)
        except error_perm:
            print("Remote .env was not found. Create it on the server before relying on this step.", file=sys.stderr)
            sys.exit(1)

        updated = rewrite_env(buf.getvalue().decode("utf-8"), seo)
        ftp.storbinary("STOR .env", BytesIO(updated.encode("utf-8")))

        try:
            ftp.delete("bootstrap/cache/config.php")
            print("Removed bootstrap/cache/config.php so the new SEO value is read.")
        except error_perm:
            pass
    finally:
        ftp.quit()

    print(f"Remote .env now has SEO={seo}.")


if __name__ == "__main__":
    main()

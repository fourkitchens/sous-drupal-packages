#!/usr/bin/env python3
"""Submit Page add/edit forms in a disposable DDEV site using Python's stdlib.

SOUS_DISPOSABLE_TEST=1 python3 /path/to/sous-drupal-packages/tests/recipes/sous-content-types/verify-authoring.py BASE_URL
"""

import argparse
from html.parser import HTMLParser
import http.cookiejar
import os
import re
import ssl
import subprocess
import sys
import tempfile
from urllib.parse import urlencode, urljoin, urlparse
from urllib.request import HTTPCookieProcessor, HTTPSHandler, ProxyHandler, build_opener
import uuid


class PageForms(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.forms = []
        self.current = None
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "form":
            self.current = {"action": attrs.get("action", ""), "values": {}}
        elif tag == "input" and self.current is not None:
            if attrs.get("type") == "hidden" and attrs.get("name") and "disabled" not in attrs:
                self.current["values"][attrs["name"]] = attrs.get("value", "")

    def handle_endtag(self, tag):
        if tag == "form" and self.current is not None:
            self.forms.append(self.current)
            self.current = None

    def page(self):
        for form in self.forms:
            if form["values"].get("form_id", "").startswith("node_page_"):
                if not all(key in form["values"] for key in ("form_id", "form_token", "form_build_id")):
                    raise RuntimeError("Page form is missing its session or CSRF fields.")
                return form
        raise RuntimeError("The response does not contain an accessible Page form.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("base_url", help="The disposable DDEV site's base URL")
    parser.add_argument("--uid", type=int, default=1, help="Active administrator user ID (generated Sous sites use 2)")
    parser.add_argument("--ca-file", help="Trusted development CA certificate, such as mkcert's rootCA.pem")
    args = parser.parse_args()
    if os.environ.get("SOUS_DISPOSABLE_TEST") != "1":
        parser.error("Set SOUS_DISPOSABLE_TEST=1 only for a disposable installation.")
    base = args.base_url.rstrip("/")
    if urlparse(base).scheme not in ("http", "https") or not urlparse(base).netloc:
        parser.error("base_url must be an HTTP or HTTPS URL.")

    def drush(*arguments):
        result = subprocess.run(
            ["ddev", "drush", "--no-ansi", "--uri=" + base, *arguments],
            text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=60,
        )
        if result.returncode:
            raise RuntimeError("DDEV Drush command failed: " + arguments[0])
        return result.stdout

    # Use the development CA explicitly when it is absent from Python's trust
    # store; certificate verification remains enabled.
    opener = build_opener(
        ProxyHandler({}), HTTPCookieProcessor(http.cookiejar.CookieJar()),
        HTTPSHandler(context=ssl.create_default_context(cafile=args.ca_file)),
    )
    last_html = ""

    def request(path, values=None):
        nonlocal last_html
        data = urlencode(values).encode() if values is not None else None
        with opener.open(urljoin(base + "/", path), data=data, timeout=60) as response:
            last_html = response.read().decode("utf-8", errors="replace")
            return response.geturl(), last_html

    def save(url, html, title, body):
        form = PageForms(html).page()
        values = form["values"]
        values.update({
            "title[0][value]": title, "field_seo_title[0][value]": title,
            "body[0][value]": "<p>" + body + "</p>", "body[0][format]": "basic_html",
            "field_teaser_text[0][value]": "A temporary authoring regression Page.",
            "status[value]": "1", "op": "Save",
        })
        return request(urljoin(url, form["action"]), values)

    marker = "Sous HTTP authoring " + uuid.uuid4().hex
    edited = "Edited " + marker
    try:
        login = drush("user:login", "--uid=" + str(args.uid), "--no-browser").strip().splitlines()[-1]
        if urlparse(login).netloc != urlparse(base).netloc:
            raise RuntimeError("Drush returned a login URL for a different site.")
        request(login)
        url, html = request("node/add/page")
        url, html = save(url, html, marker, marker)
        node = re.search(r"/node/(\d+)(?:/|$)", urlparse(url).path)
        if not node or "<p>" + marker + "</p>" not in html:
            raise RuntimeError("Creating the Page did not redirect to its rendered Body.")
        url, html = request("node/" + node[1] + "/edit")
        url, html = save(url, html, edited, edited)
        if "<p>" + edited + "</p>" not in html or "<p>" + marker + "</p>" in html:
            raise RuntimeError("Editing the Page did not save and render the changed Body.")
        print("PASS: HTTP login, Page creation, editing, saving, and Body rendering.")
    except Exception:
        if last_html:
            with tempfile.NamedTemporaryFile(mode="w", suffix=".html", prefix="sous-authoring-", delete=False) as output:
                output.write(last_html)
                print("Last response saved for diagnosis: " + output.name, file=sys.stderr)
        raise
    finally:
        # Match only this run's unpredictable titles, including partial saves.
        titles = "['" + marker + "', '" + edited + "']"
        drush("php:eval", "$ids = \\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'page')->condition('title', " + titles + ", 'IN')->execute(); $storage = \\Drupal::entityTypeManager()->getStorage('node'); $storage->delete($storage->loadMultiple($ids));")


if __name__ == "__main__":
    main()

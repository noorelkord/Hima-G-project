#!/usr/bin/env python3
"""Static file server with custom 404 page. Run: python server.py"""
import http.server, os, sys

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8080

class Handler(http.server.SimpleHTTPRequestHandler):
    def send_error(self, code, message=None, explain=None):
        if code == 404:
            self.send_response(404)
            self.send_header("Content-type", "text/html; charset=utf-8")
            self.end_headers()
            try:
                with open(os.path.join(os.path.dirname(__file__), "404.html"), "rb") as f:
                    self.wfile.write(f.read())
            except FileNotFoundError:
                self.wfile.write(b"<h1>404 Not Found</h1>")
        else:
            super().send_error(code, message, explain)

    def log_message(self, fmt, *args):
        print(f"[{self.address_string()}] {fmt % args}")

os.chdir(os.path.dirname(os.path.abspath(__file__)))
with http.server.HTTPServer(("", PORT), Handler) as httpd:
    print(f"Serving http://127.0.0.1:{PORT}/ — Ctrl+C to stop")
    httpd.serve_forever()

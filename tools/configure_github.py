"""One-off helper: make the task repository public and install the three
OIDC repository secrets that the GitHub Actions workflows expect.

The token is read from the Git credential manager (the same credential that
`git push` already uses) so no secret is written to disk or to the console.
"""
import base64
import json
import subprocess
import sys

import requests
from nacl import encoding, public

REPO = "hung7405/SWE40006-Task3-Deployment-in-azure"
OWNER, NAME = REPO.split("/")

SECRETS = {
    "AZURE_CLIENT_ID": "7843b7b1-588b-437c-a2d1-3e269629d99f",
    "AZURE_TENANT_ID": "bc4184d8-938b-4c56-b290-ea9e15b17b6b",
    "AZURE_SUBSCRIPTION_ID": "74e3f720-03fa-47b3-be35-2d626dcbb655",
}


def credential():
    out = subprocess.run(
        ["git", "credential", "fill"],
        input="protocol=https\nhost=github.com\n\n",
        capture_output=True,
        text=True,
        check=True,
    ).stdout
    fields = dict(line.split("=", 1) for line in out.splitlines() if "=" in line)
    return fields["password"]


def encrypt(public_key_b64: str, value: str) -> str:
    key = public.PublicKey(public_key_b64.encode(), encoding.Base64Encoder())
    sealed = public.SealedBox(key).encrypt(value.encode())
    return base64.b64encode(sealed).decode()


token = credential()
s = requests.Session()
s.headers.update(
    {
        "Authorization": f"Bearer {token}",
        "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28",
    }
)

me = s.get("https://api.github.com/user", timeout=30)
print("authenticated as:", me.json().get("login"), "status", me.status_code)
print("scopes:", me.headers.get("x-oauth-scopes"))

repo = s.get(f"https://api.github.com/repos/{REPO}", timeout=30).json()
print("visibility before:", "private" if repo.get("private") else "public")

if repo.get("private"):
    r = s.patch(
        f"https://api.github.com/repos/{REPO}",
        data=json.dumps({"private": False}),
        timeout=30,
    )
    print("make public:", r.status_code, r.json().get("private"))

key = s.get(f"https://api.github.com/repos/{REPO}/actions/secrets/public-key", timeout=30).json()
for name, value in SECRETS.items():
    payload = {"encrypted_value": encrypt(key["key"], value), "key_id": key["key_id"]}
    r = s.put(
        f"https://api.github.com/repos/{REPO}/actions/secrets/{name}",
        data=json.dumps(payload),
        timeout=30,
    )
    print(f"secret {name}: {r.status_code}")

names = s.get(f"https://api.github.com/repos/{REPO}/actions/secrets", timeout=30).json()
print("secrets present:", [x["name"] for x in names.get("secrets", [])])

repo = s.get(f"https://api.github.com/repos/{REPO}", timeout=30).json()
print("visibility after:", "private" if repo.get("private") else "public")

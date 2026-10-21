import subprocess, requests, re
out = subprocess.run(["git","credential","fill"], input="protocol=https\nhost=github.com\n\n", capture_output=True, text=True).stdout
tok = dict(l.split("=",1) for l in out.splitlines() if "=" in l)["password"]
s = requests.Session(); s.headers.update({"Authorization": f"Bearer {tok}", "Accept":"application/vnd.github+json"})
repo = "hung7405/SWE40006-Task3-Deployment-in-azure"
runs = s.get(f"https://api.github.com/repos/{repo}/actions/runs?per_page=4", timeout=30).json()["workflow_runs"]
for run in runs:
    if run["run_number"] != 2: continue
    jobs = s.get(run["jobs_url"], timeout=30).json()["jobs"]
    for j in jobs:
        url = f"https://api.github.com/repos/{repo}/actions/jobs/{j['id']}/logs"
        log = s.get(url, timeout=60, allow_redirects=True).text
        lines = log.splitlines()
        print("="*20, run["name"], j["name"], len(lines), "lines")
        for i, line in enumerate(lines):
            if re.search(r"##\[error\]|error CS|MSB|Failed to|failed with|Error:", line):
                print("\n".join(lines[max(0,i-4):i+4]))
                print("-"*10)

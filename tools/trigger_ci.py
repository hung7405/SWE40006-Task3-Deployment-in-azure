import subprocess, time, requests, json
out = subprocess.run(["git","credential","fill"], input="protocol=https\nhost=github.com\n\n", capture_output=True, text=True).stdout
tok = dict(l.split("=",1) for l in out.splitlines() if "=" in l)["password"]
s = requests.Session(); s.headers.update({"Authorization": f"Bearer {tok}", "Accept":"application/vnd.github+json"})
repo = "hung7405/SWE40006-Task3-Deployment-in-azure"
for wf in ["deploy-csharp.yml","deploy-php.yml"]:
    r = s.post(f"https://api.github.com/repos/{repo}/actions/workflows/{wf}/dispatches", json={"ref":"main"}, timeout=30)
    print(wf, r.status_code)
for attempt in range(40):
    time.sleep(20)
    runs = s.get(f"https://api.github.com/repos/{repo}/actions/runs?per_page=5", timeout=30).json()["workflow_runs"]
    state = [(x["name"], x["status"], x["conclusion"], x["run_number"]) for x in runs]
    print(attempt, state)
    if runs and all(x["status"] == "completed" for x in runs[:2]):
        break

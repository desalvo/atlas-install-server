# Upload to GitHub as `desalvo/atlas-install-server`

The delivered source archive is laid out as a repository root and can be imported directly.

## Existing empty repository

```bash
git init -b main
git add .
git commit -m "Initial Rocky Linux 10 hardened server release"
git remote add origin git@github.com:desalvo/atlas-install-server.git
git push -u origin main
```

## Create with GitHub CLI

Authenticate first with `gh auth login`, then choose repository visibility explicitly:

```bash
git init -b main
git add .
git commit -m "Initial Rocky Linux 10 hardened server release"
gh repo create desalvo/atlas-install-server --source=. --remote=origin --push --private
```

Replace `--private` with `--public` if the repository is intended to be public.

## Docker Hub CI secret

In GitHub repository settings create the Actions secret `DOCKERHUB_TOKEN`. It must be a Docker Hub access token with push permission for `desalvo/atlas-install-server`.

After this, pushes and pull requests run tests. A successful push to `main` publishes `latest`, `edge`, and a SHA tag. A successful `v*` tag publishes semantic-version tags and triggers the GitHub source release archive workflow.


## Kubernetes wizard

After pushing the repository, operators can download the standalone Kubernetes wizard directly from GitHub:

```bash
curl -fLO https://raw.githubusercontent.com/desalvo/atlas-install-server/main/scripts/atlas-install-k8s-wizard.sh
chmod +x atlas-install-k8s-wizard.sh
./atlas-install-k8s-wizard.sh
```

The wizard asks for the repository/ref on first run and then retrieves its manifest templates from that location.

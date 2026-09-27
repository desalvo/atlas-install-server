# Kubernetes installation wizard

`scripts/atlas-install-k8s-wizard.sh` is the recommended installer/configurator for Kubernetes deployments of ATLAS Installation Server 3.0.0.

## First run

Download the wizard directly from GitHub:

```bash
curl -fLO https://raw.githubusercontent.com/desalvo/atlas-install-server/main/scripts/atlas-install-k8s-wizard.sh
chmod +x atlas-install-k8s-wizard.sh
./atlas-install-k8s-wizard.sh
```

On the first run the wizard proposes `desalvo/atlas-install-server` and asks you to confirm the GitHub repository. It also asks which branch/tag/ref is used to retrieve Kubernetes templates; the default is `main`.

The known installation defaults are proposed initially, including:

```text
namespace:            atlas-install
resource prefix:      atlas-install
image:                desalvo/atlas-install-server:3.0.0
public hostname:      atlas-install-el10.apps.desalvo.eu
IngressClass:         haproxy
PVC size:             5Gi
PVC access mode:      ReadWriteOnce
DB name:              atlas_install_panda
DB host:              192.168.1.145
```

All non-secret answers are saved with mode `0600` in:

```text
~/.config/atlas-install-server/k8s-wizard.env
```

Later runs propose the last selected values instead of the initial defaults.

Database passwords are deliberately **not** stored in this file. Existing Kubernetes Secrets are treated conservatively: when a bootstrap or TLS Secret is found, the wizard first asks whether it should be updated. The default is to keep the existing Secret unchanged. If bootstrap update is selected, the existing non-secret values become the proposed defaults and a blank password keeps the current password.

The host certificate/key paths selected during the initial configuration are stored as non-secret local state and are reused on later runs. They are not changed merely because the wizard is run again. When updating an existing TLS Secret, the wizard asks separately whether those stored paths should be changed; the default is to keep them.

## Templates and generated manifests

The wizard downloads these version-controlled templates from the selected GitHub repository/ref:

```text
kubernetes/templates/atlas-install-container.yaml.tpl
kubernetes/templates/maintenance-cronjobs.yaml.tpl
```

Downloaded templates are cached under `~/.config/atlas-install-server/cache/`. Every execution checks the remote content. Missing or changed templates are downloaded again; unchanged templates are reused.

Rendered manifests are written by default to:

```text
./atlas-install-kubernetes/
```

The directory contains the main deployment manifest and, when enabled, the maintenance CronJobs. The generated README records the repository, ref, namespace, image and hostname used for that render.

## Kubernetes secrets

When secret management is enabled, the wizard confirms the active `kubectl` context and ensures the namespace exists.

### Bootstrap Secret

The wizard creates/updates:

```text
<namespace>/<resource-prefix>-bootstrap
```

using `kubectl create secret ... --dry-run=client -o yaml | kubectl apply -f -`. The resulting secret contains `atlas-install.env` with DB and application settings.

If the Secret already exists, the wizard asks whether to update it; the default is **No**. If update is selected, existing DB/application values are read from the Secret and proposed as defaults. Existing DB passwords are reused when the corresponding password prompt is left blank. This permits selective changes without exposing or retyping unchanged passwords.

### Host TLS Secret

The wizard creates/updates:

```text
<namespace>/<resource-prefix>-tls
```

from a certificate/full-chain PEM file and private-key PEM file. Before applying the Secret it verifies:

- the certificate parses as X.509;
- the private key parses successfully;
- certificate and key have the same public key;
- when supported by the installed OpenSSL, the certificate matches the configured public hostname.

Only the last certificate/key **paths** are stored in wizard state; certificate/private-key contents are never copied into the state file.

If the TLS Secret already exists, the wizard asks whether to update it; the default is **No**. If update is selected, the previously stored certificate/key paths are reused unless the operator explicitly chooses to change them. If the selected files are byte-for-byte identical to the current Secret, no Secret update or Deployment restart is performed. When the TLS material really changes, the wizard restarts the existing Deployment so Apache loads the new certificate.

## Generate only

To generate/update manifests without touching Kubernetes:

```bash
./atlas-install-k8s-wizard.sh --generate-only
```

A non-interactive render using saved/default values is also supported:

```bash
./atlas-install-k8s-wizard.sh --generate-only --non-interactive
```

## Generate, update secrets and apply

To request the full deployment flow directly:

```bash
./atlas-install-k8s-wizard.sh --apply
```

The wizard still confirms the current `kubectl` context in interactive mode before changing cluster resources.

## Private GitHub repositories

For a private repository, export a token readable by the current process before running the wizard:

```bash
export GITHUB_TOKEN='...'
./atlas-install-k8s-wizard.sh
unset GITHUB_TOKEN
```

The token is used only for HTTP authorization while downloading templates and is not written to wizard state.

## Automation

For non-interactive secret creation/update, database passwords and TLS file paths can be supplied as environment variables:

```text
ATLAS_DB_RW_PASSWORD
ATLAS_DB_RO_PASSWORD
ATLAS_DB_BROKER_PASSWORD
ATLAS_TLS_CERT_FILE
ATLAS_TLS_KEY_FILE
ATLAS_UPDATE_BOOTSTRAP_SECRET=1
ATLAS_UPDATE_TLS_SECRET=1
```

In non-interactive mode existing Secrets are kept by default. Set the corresponding `ATLAS_UPDATE_*_SECRET=1` flag only when an update is intentionally required. This makes repeated unattended executions idempotent by default.

Use `--non-interactive --apply` only after reviewing the stored/default values and the target Kubernetes context.

## Reset stored choices

To discard saved non-secret choices:

```bash
./atlas-install-k8s-wizard.sh --reset-state
```

The next run starts from the known project defaults and asks for the GitHub repository again.

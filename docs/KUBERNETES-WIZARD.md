# Kubernetes installation wizard

`scripts/atlas-install-k8s-wizard.sh` is the recommended Kubernetes installer/configurator for ATLAS Installation Server 3.0.0.

## First run and persistent defaults

Download it directly from GitHub:

```bash
curl -fLO https://raw.githubusercontent.com/desalvo/atlas-install-server/main/scripts/atlas-install-k8s-wizard.sh
chmod +x atlas-install-k8s-wizard.sh
./atlas-install-k8s-wizard.sh
```

The first run proposes `desalvo/atlas-install-server` and asks for confirmation of the GitHub repository and ref. Non-secret choices are persisted in `~/.config/atlas-install-server/k8s-wizard.env` (mode `0600`) and become the defaults for later runs. Database passwords and GitHub tokens are never written there.

Known defaults include namespace/resource prefix `atlas-install`, image `desalvo/atlas-install-server:3.0.0`, hostname `atlas-install-el10.apps.desalvo.eu`, HAProxy ingress, a 5Gi RWO PVC and the known database defaults.

## Database endpoint selection and changes

The wizard asks explicitly for a single **Database server/IP** on every run. The value is saved as a non-secret choice and is mapped to the application's RW, RO and broker database endpoints. The default remains `192.168.1.145` for backward compatibility.

When the wizard is rerun against an existing installation and the selected database endpoint differs from the endpoint stored in the Kubernetes bootstrap Secret, it reports the old and new values and asks whether to update the Secret. A host-only change preserves all existing database passwords and does not rotate credentials. This makes it safe to switch, for example, from a legacy PXC IP to a MariaDB/Galera Service or VIP.

For automation:

```bash
./atlas-install-k8s-wizard.sh --apply --db-host 10.97.227.203
# or
ATLAS_DB_HOST=10.97.227.203 ./atlas-install-k8s-wizard.sh --apply
```

`--db-host`/`ATLAS_DB_HOST` may contain either an IP address or a resolvable hostname such as `atlas-primary.adsnetdb.svc.cluster.local`. Passwords are never persisted in the wizard state file.

## Modular manifests and Kustomize

The wizard downloads and caches these templates from the selected repository/ref:

```text
kubernetes/templates/00-namespace.yaml.tpl
kubernetes/templates/10-pvc.yaml.tpl
kubernetes/templates/20-deployment.yaml.tpl
kubernetes/templates/30-service.yaml.tpl
kubernetes/templates/40-ingress.yaml.tpl
kubernetes/templates/50-maintenance-cronjobs.yaml.tpl
kubernetes/templates/kustomization.yaml.tpl
```

It renders by default into `./atlas-install-kubernetes/`:

```text
00-namespace.yaml
10-pvc.yaml
20-deployment.yaml
30-service.yaml
40-ingress.yaml
50-maintenance-cronjobs.yaml   # only when enabled
kustomization.yaml
README.generated.txt
```

Manual application uses:

```bash
kubectl apply -f atlas-install-kubernetes/00-namespace.yaml
kubectl apply -k atlas-install-kubernetes/
```

The wizard performs the same ordering when `--apply` is used. Re-rendering unchanged content does not rewrite files.

## Optional nodeSelector

The server pod may optionally be pinned/selected using Kubernetes node labels. The feature is disabled by default. When enabled, the wizard accepts one or more comma-separated selectors such as:

```text
kubernetes.io/hostname=kube-node-01,node-role.kubernetes.io/worker=worker
```

Only when enabled does the generated Deployment contain:

```yaml
nodeSelector:
  kubernetes.io/hostname: "kube-node-01"
```

The selection is remembered and proposed on later runs. In non-interactive mode use `ATLAS_NODE_SELECTOR=key=value[,key=value...]`; if unset, no nodeSelector is generated unless it was previously enabled in saved state. For manual/static Kustomize deployments, `kubernetes/overlays/node-selector/` contains an editable example patch.

## Wizard self-update

Self-update is optional and disabled by default. The wizard asks whether it should automatically check the selected GitHub repository/ref for a newer `scripts/atlas-install-k8s-wizard.sh`. The choice is persisted.

Enable or disable it explicitly with:

```bash
./atlas-install-k8s-wizard.sh --self-update
./atlas-install-k8s-wizard.sh --no-self-update
```

For unattended runs, `ATLAS_WIZARD_AUTO_UPDATE=1` enables it. A remote update is accepted only when `scripts/atlas-install-k8s-wizard.sh.sha256` is also available and matches the downloaded script. Replacement is atomic; the updated copy is used on the next execution. If download or verification fails, the current wizard continues unchanged.

## Existing Secrets and idempotency

Existing `<app>-bootstrap` and `<app>-tls` Secrets are never overwritten merely because the wizard is rerun. The wizard asks before updating each one and defaults to keeping it unchanged. In non-interactive mode updates require `ATLAS_UPDATE_BOOTSTRAP_SECRET=1` and/or `ATLAS_UPDATE_TLS_SECRET=1`.

When bootstrap update is selected, existing non-secret values are proposed as defaults and blank password input preserves the current password. TLS certificate/key paths selected during first configuration are remembered and reused unless the operator explicitly changes them. Identical TLS material causes no Secret write and no Deployment restart.

## Modes

Generate only:

```bash
./atlas-install-k8s-wizard.sh --generate-only
```

Generate/apply and manage Secrets:

```bash
./atlas-install-k8s-wizard.sh --apply
```

Unattended generation/application is supported with `--non-interactive`. Use `GITHUB_TOKEN` for private repositories; it is never persisted.

Reset saved non-secret choices with:

```bash
./atlas-install-k8s-wizard.sh --reset-state
```

## Bootstrap Secret synchronization

The Kubernetes bootstrap Secret is the authoritative source for the managed `atlas-install.env`. On container startup, the entrypoint compares the mounted Secret with `/var/lib/atlas-install/config/atlas-install.env` and atomically refreshes the persistent file when they differ. While the container is running, it checks the mounted Secret every 5 seconds by default (`ATLAS_BOOTSTRAP_SYNC_SECONDS`).

When the wizard itself changes the bootstrap Secret, it also performs a Deployment rollout restart. This guarantees that settings consumed only during startup are reloaded as well as runtime database settings. Database passwords remain in the Secret and are not written to wizard state.

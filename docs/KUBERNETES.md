# Kubernetes deployment

## Recommended: interactive wizard

For new installations and reconfiguration, use `scripts/atlas-install-k8s-wizard.sh`. The wizard:

- proposes known project defaults on first run;
- stores the last non-secret selections under `~/.config/atlas-install-server/k8s-wizard.env`;
- asks for the database IP/hostname explicitly and allows it to be changed on later runs without rotating DB passwords;
- asks for and confirms the GitHub repository on first run;
- downloads/refreshes Kubernetes templates from the selected repository/ref;
- renders the installation-specific manifests;
- detects existing bootstrap/TLS Secrets and asks before changing them (default: keep unchanged);
- reuses existing bootstrap values as defaults without persisting database passwords locally;
- preserves the initially selected host certificate/key paths unless the operator explicitly changes them;
- validates the server certificate/private key pair and updates the TLS Secret only when requested;
- can optionally apply the generated manifests to the current Kubernetes context.

Download and run it without cloning the whole repository:

```bash
curl -fLO https://raw.githubusercontent.com/desalvo/atlas-install-server/main/scripts/atlas-install-k8s-wizard.sh
chmod +x atlas-install-k8s-wizard.sh
./atlas-install-k8s-wizard.sh
```

For details see `KUBERNETES-WIZARD.md`.


## Automatic IGTF trust-anchor and CRL refresh

The container initializes `/etc/grid-security/certificates` before Apache starts and then refreshes IGTF material automatically while the pod is running. The default policy is:

```text
ATLAS_IGTF_REFRESH_SECONDS=21600
ATLAS_IGTF_BUNDLE_MAX_AGE_SECONDS=86400
```

Every six hours the container invokes the IGTF updater. CRLs are refreshed on every invocation; the classic/MICS/IOTA trust-anchor bundles are re-downloaded when the local bundle stamp is older than one day. Downloads and `openssl rehash` are performed in a staging directory and the active trust store is replaced only after validation succeeds. A transient refresh failure therefore leaves the previous working trust store in place. Apache is gracefully reloaded after a successful periodic refresh.

## Manual installation

## 1. Build the image

The image is based on Rocky Linux 10 and contains Apache, PHP-FPM, the application and `fetch-crl`. No production secret is part of the build context.

```bash
./container/build-image.sh desalvo/atlas-install-server:latest
podman push desalvo/atlas-install-server:latest
```

Docker can be selected explicitly:

```bash
CONTAINER_ENGINE=docker ./container/build-image.sh desalvo/atlas-install-server:latest
```

## 2. Create TLS Secret

The certificate must cover `atlas-install-el10.apps.desalvo.eu`. With TLS passthrough this certificate is presented directly by Apache in the pod.

```bash
kubectl create namespace atlas-install
kubectl -n atlas-install create secret tls atlas-install-tls \
  --cert=/secure/fullchain.pem \
  --key=/secure/privkey.pem
```

## 3. Create the bootstrap configuration

Copy the example locally, enter real DB passwords, protect the file and create the Secret:

```bash
cp etc/atlas-install/atlas-install.env.example /secure/atlas-install.env
chmod 600 /secure/atlas-install.env
$EDITOR /secure/atlas-install.env

kubectl -n atlas-install create secret generic atlas-install-bootstrap \
  --from-file=atlas-install.env=/secure/atlas-install.env
```

Recommended defaults:

```text
ATLAS_PUBLIC_HOSTNAME=atlas-install-el10.apps.desalvo.eu
ATLAS_DB_RW_HOST=192.168.1.145
ATLAS_DB_RO_HOST=192.168.1.145
ATLAS_DB_BROKER_HOST=192.168.1.145
```

The bootstrap Secret is used only when the managed configuration PVC contains no active configuration file.

## 4. Select the image tag

The supplied manifest uses the tested release tag `desalvo/atlas-install-server:3.0.0`. Change the image only when deploying another tested release or registry target.

## 5. Deploy

```bash
kubectl apply -f kubernetes/manifests/00-namespace.yaml
kubectl apply -k kubernetes/manifests/
kubectl -n atlas-install rollout status deployment/atlas-install
```


## Optional nodeSelector

No node selector is enabled by default. To constrain the server pod manually, add a `nodeSelector` under `spec.template.spec` in `20-deployment.yaml`, for example:

```yaml
nodeSelector:
  kubernetes.io/hostname: kube-node-01
```

The wizard can generate this block automatically and remembers the selection. Leaving the option disabled emits no `nodeSelector`. A static Kustomize example is also provided under `kubernetes/overlays/node-selector/`; edit its patch and apply that overlay with `kubectl apply -k kubernetes/overlays/node-selector/`.

## 6. Validate TLS passthrough

HAProxy Ingress must use:

```yaml
haproxy-ingress.github.io/ssl-passthrough: "true"
```

There must be one TLS backend for the hostname. The backend performs the handshake and therefore receives the original client certificate.

Test a public endpoint without a client certificate:

```bash
curl -vk https://atlas-install-el10.apps.desalvo.eu/atlas_install/healthz.php
```

Then test the protected area with a valid client certificate:

```bash
curl -v \
  --cert /secure/client-cert.pem \
  --key /secure/client-key.pem \
  https://atlas-install-el10.apps.desalvo.eu/atlas_install/protected/configuration.php
```

A request to `/protected/` without an acceptable certificate must fail.

## 7. Persistent data

`atlas-install-data` stores:

```text
/var/lib/atlas-install/config/atlas-install.env
/var/lib/atlas-install/log/
/var/lib/atlas-install/logbackup/
```

The provided manifest uses one replica and `Recreate` update strategy so the managed configuration has a single writer.

If you later need multiple replicas, move configuration to a shared/transactional backend or an RWX volume and review legacy file-writing behavior before scaling.

## 8. Maintenance CronJobs

The optional maintenance resources are in `kubernetes/manifests/50-maintenance-cronjobs.yaml`. Add that file to the generated/static `kustomization.yaml` resources list, or enable maintenance in the wizard.

Before enabling them on a cluster with a strict RWO storage class, ensure the maintenance pods can attach the same storage or move to RWX storage.

## 9. Network policy / firewall

Permit database TCP/3306 from the Kubernetes worker/pod network required by your CNI to `192.168.1.145`; restrict all other sources at the DB firewall when possible.

The application database accounts should also be host-restricted at MySQL/Percona/MariaDB level.

## Persistent CRL cache (3.0.0-r17)

The serving pod keeps a persistent IGTF/CRL cache under `/var/lib/atlas-install/igtf-cache`, which is on the application data PVC. On pod startup the active `/etc/grid-security/certificates` emptyDir is restored from that cache before Apache starts. `fetch-crl` is skipped when the last successful persistent CRL state is younger than `ATLAS_CRL_MAX_AGE_SECONDS` (default 21600 seconds / 6 hours). A forced IGTF update or stale/missing CRL cache still performs a real refresh.

This avoids repeating a long `fetch-crl` operation after ordinary Kubernetes rescheduling or restarts while retaining periodic refresh and revocation checking.

## Application logs

PHP-FPM worker output and PHP `error_log()` are forwarded to container stderr. `kubectl logs` therefore includes structured `[ATLAS_APP]` events for authentication failures, access denials, database failures and uncaught PHP errors. A request ID is returned in `X-Request-ID` and included in application error events where available.

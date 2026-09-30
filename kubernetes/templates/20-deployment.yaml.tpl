apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{APP_NAME}}
  namespace: {{NAMESPACE}}
spec:
  replicas: {{REPLICAS}}
  strategy:
    type: Recreate
  selector:
    matchLabels:
      app: {{APP_NAME}}
  template:
    metadata:
      labels:
        app: {{APP_NAME}}
    spec:
{{NODE_SELECTOR_BLOCK}}
      terminationGracePeriodSeconds: 30
      containers:
        - name: {{APP_NAME}}
          image: {{IMAGE}}
          imagePullPolicy: {{IMAGE_PULL_POLICY}}
          ports:
            - name: https
              containerPort: 8443
              protocol: TCP
          envFrom:
            - secretRef:
                name: {{APP_NAME}}-db-admin
                optional: true
          env:
            - name: ATLAS_ENV_FILE
              value: /var/lib/atlas-install/config/atlas-install.env
            - name: ATLAS_BOOTSTRAP_ENV
              value: /run/secrets/bootstrap/atlas-install.env
            - name: ATLAS_TLS_CERT_FILE
              value: /run/secrets/tls/tls.crt
            - name: ATLAS_TLS_KEY_FILE
              value: /run/secrets/tls/tls.key
            - name: ATLAS_IGTF_DIR
              value: /etc/grid-security/certificates
            - name: ATLAS_IGTF_REFRESH_SECONDS
              value: "21600"
            - name: ATLAS_IGTF_BUNDLE_MAX_AGE_SECONDS
              value: "86400"
            - name: ATLAS_CRL_FETCH_TIMEOUT_SECONDS
              value: "120"
            - name: ATLAS_HTTPS_PORT
              value: "8443"
          volumeMounts:
            - name: data
              mountPath: /var/lib/atlas-install
            - name: cache
              mountPath: /var/cache/atlas-install
            - name: runtime
              mountPath: /run/atlas-install
            - name: tls
              mountPath: /run/secrets/tls
              readOnly: true
            - name: bootstrap
              mountPath: /run/secrets/bootstrap
              readOnly: true
            - name: igtf
              mountPath: /etc/grid-security/certificates
          startupProbe:
            httpGet:
              scheme: HTTPS
              path: /atlas_install/healthz.php
              port: https
            initialDelaySeconds: 5
            periodSeconds: 5
            timeoutSeconds: 5
            failureThreshold: 60
          readinessProbe:
            httpGet:
              scheme: HTTPS
              path: /atlas_install/readyz.php
              port: https
            initialDelaySeconds: 10
            periodSeconds: 15
            timeoutSeconds: 5
            failureThreshold: 4
          livenessProbe:
            httpGet:
              scheme: HTTPS
              path: /atlas_install/healthz.php
              port: https
            initialDelaySeconds: 30
            periodSeconds: 30
            timeoutSeconds: 5
            failureThreshold: 3
          resources:
            requests:
              cpu: {{CPU_REQUEST}}
              memory: {{MEMORY_REQUEST}}
            limits:
              cpu: {{CPU_LIMIT}}
              memory: {{MEMORY_LIMIT}}
          securityContext:
            allowPrivilegeEscalation: false
            seccompProfile:
              type: RuntimeDefault
            capabilities:
              drop: ["ALL"]
              add: ["CHOWN", "DAC_OVERRIDE", "FOWNER", "KILL", "SETGID", "SETUID"]
      volumes:
        - name: data
          persistentVolumeClaim:
            claimName: {{APP_NAME}}-data
        - name: cache
          emptyDir: {}
        - name: runtime
          emptyDir: {}
        - name: igtf
          emptyDir: {}
        - name: tls
          secret:
            secretName: {{APP_NAME}}-tls
        - name: bootstrap
          secret:
            secretName: {{APP_NAME}}-bootstrap

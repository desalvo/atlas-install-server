apiVersion: batch/v1
kind: CronJob
metadata:
  name: {{APP_NAME}}-plots
  namespace: {{NAMESPACE}}
spec:
  schedule: "{{PLOTS_SCHEDULE}}"
  concurrencyPolicy: Forbid
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: plots
              image: {{IMAGE}}
              imagePullPolicy: {{IMAGE_PULL_POLICY}}
              command: ["/usr/local/sbin/atlas-container-entrypoint", "maintenance", "plots"]
              env:
                - name: ATLAS_ENV_FILE
                  value: /var/lib/atlas-install/config/atlas-install.env
              volumeMounts:
                - name: data
                  mountPath: /var/lib/atlas-install
                - name: cache
                  mountPath: /var/cache/atlas-install
          volumes:
            - name: data
              persistentVolumeClaim:
                claimName: {{APP_NAME}}-data
            - name: cache
              emptyDir: {}
---
apiVersion: batch/v1
kind: CronJob
metadata:
  name: {{APP_NAME}}-log-cleanup
  namespace: {{NAMESPACE}}
spec:
  schedule: "{{CLEANUP_SCHEDULE}}"
  concurrencyPolicy: Forbid
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: cleanup
              image: {{IMAGE}}
              imagePullPolicy: {{IMAGE_PULL_POLICY}}
              command: ["/usr/local/sbin/atlas-container-entrypoint", "maintenance", "cleanup"]
              env:
                - name: ATLAS_ENV_FILE
                  value: /var/lib/atlas-install/config/atlas-install.env
              volumeMounts:
                - name: data
                  mountPath: /var/lib/atlas-install
          volumes:
            - name: data
              persistentVolumeClaim:
                claimName: {{APP_NAME}}-data

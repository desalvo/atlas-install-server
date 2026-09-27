apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: {{APP_NAME}}-data
  namespace: {{NAMESPACE}}
spec:
  accessModes: ["{{PVC_ACCESS_MODE}}"]
{{STORAGE_CLASS_BLOCK}}  resources:
    requests:
      storage: {{STORAGE_SIZE}}

apiVersion: v1
kind: Service
metadata:
  name: {{APP_NAME}}
  namespace: {{NAMESPACE}}
spec:
  selector:
    app: {{APP_NAME}}
  ports:
    - name: https
      port: 443
      targetPort: https
      protocol: TCP

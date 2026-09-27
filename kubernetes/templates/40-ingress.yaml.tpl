apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: {{APP_NAME}}
  namespace: {{NAMESPACE}}
  annotations:
    haproxy-ingress.github.io/ssl-passthrough: "true"
spec:
  ingressClassName: {{INGRESS_CLASS}}
  rules:
    - host: {{PUBLIC_HOSTNAME}}
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: {{APP_NAME}}
                port:
                  number: 443

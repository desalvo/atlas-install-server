FROM rockylinux/rockylinux:10

LABEL org.opencontainers.image.title="ATLAS Installation System" \
      org.opencontainers.image.version="3.0.0" \
      org.opencontainers.image.description="ATLAS software installation server for EL10/Kubernetes with mTLS and IGTF trust" \
      org.opencontainers.image.vendor="desalvo" \
      org.opencontainers.image.licenses="EUPL-1.2" \
      org.opencontainers.image.source="https://github.com/desalvo/atlas-install-server"

USER 0

RUN dnf -y update \
 && dnf -y install dnf-plugins-core \
 && dnf config-manager --set-enabled crb \
 && dnf -y install \
      httpd mod_ssl \
      php php-cli php-fpm php-mysqlnd php-gd php-mbstring php-ldap php-xml \
      rsync curl openssl tar ca-certificates shadow-utils findutils procps-ng python3 \
 && dnf -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm \
 && dnf -y install fetch-crl qrencode \
 && dnf clean all \
 && rm -rf /var/cache/dnf /etc/httpd/conf.d/ssl.conf /var/www/html/* \
 && sed -ri 's|^[[:space:]]*Listen[[:space:]]+80([[:space:]]*)$|# disabled in container: Listen 80|' /etc/httpd/conf/httpd.conf

RUN getent group atlas-install >/dev/null || groupadd --system atlas-install \
 && usermod -a -G atlas-install apache \
 && install -d -o root -g atlas-install -m 2770 /var/lib/atlas-install/config /var/lib/atlas-install/log /var/lib/atlas-install/logbackup /var/cache/atlas-install \
 && install -d -o root -g root -m 0755 /etc/grid-security/certificates /run/atlas-install /run/php-fpm /var/log/httpd

COPY var/www/html/atlas_install-3.0.0/ /var/www/html/atlas_install-3.0.0/
COPY container/httpd-container.conf.template /opt/atlas/httpd-container.conf.template
COPY container/bootstrap-db.php /opt/atlas/bootstrap-db.php
COPY sql/local-auth-schema.sql /opt/atlas/local-auth-schema.sql
COPY container/entrypoint.sh /usr/local/sbin/atlas-container-entrypoint
COPY container/update-igtf.sh /usr/local/sbin/atlas-update-igtf
COPY container/maintenance.sh /usr/local/sbin/atlas-maintenance
COPY etc/atlas-install/atlas-install.env.example /opt/atlas/atlas-install.env.example

RUN ln -sfn atlas_install-3.0.0 /var/www/html/atlas_install \
 && chown -R root:root /var/www/html/atlas_install-3.0.0 /opt/atlas \
 && find /var/www/html/atlas_install-3.0.0 -type d -exec chmod 0755 {} + \
 && find /var/www/html/atlas_install-3.0.0 -type f -exec chmod 0644 {} + \
 && chmod 0755 /usr/local/sbin/atlas-container-entrypoint /usr/local/sbin/atlas-update-igtf /usr/local/sbin/atlas-maintenance \
 && printf '%s\n' 'clear_env = no' 'catch_workers_output = yes' 'decorate_workers_output = no' 'php_admin_flag[log_errors] = on' 'php_admin_value[error_log] = /var/lib/atlas-install/log/php-application.log' >> /etc/php-fpm.d/www.conf

ENV ATLAS_ENV_FILE=/var/lib/atlas-install/config/atlas-install.env \
    ATLAS_TLS_CERT_FILE=/run/secrets/tls/tls.crt \
    ATLAS_TLS_KEY_FILE=/run/secrets/tls/tls.key \
    ATLAS_BOOTSTRAP_ENV=/run/secrets/bootstrap/atlas-install.env \
    ATLAS_IGTF_DIR=/etc/grid-security/certificates \
    ATLAS_HTTPS_PORT=8443

EXPOSE 8443
STOPSIGNAL SIGWINCH
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD curl -kfsS https://127.0.0.1:8443/atlas_install/healthz.php >/dev/null || exit 1
ENTRYPOINT ["/usr/local/sbin/atlas-container-entrypoint"]
CMD ["serve"]
